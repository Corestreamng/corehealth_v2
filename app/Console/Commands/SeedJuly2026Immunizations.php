<?php

namespace App\Console\Commands;

use App\Http\Controllers\NursingWorkbenchController;
use App\Models\ImmunizationRecord;
use App\Models\MaternityEnrollment;
use App\Models\NhmisMonthlyReport;
use App\Models\Patient;
use App\Models\PatientImmunizationSchedule;
use App\Models\User;
use App\Models\VaccineScheduleItem;
use App\Models\VaccineScheduleTemplate;
use App\Services\Nhmis\NhmisDataAggregatorService;
use App\Services\Nhmis\Schemas\Nhmis2019Schema;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SeedJuly2026Immunizations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nhmis:seed-july-2026-immunizations {--verify-only : Only verify without re-seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed authentic immunization data for mothers and babies in July 2026 using Nursing Workbench schedule administration and verify NHMIS Section items 65-87';

    public function handle(NursingWorkbenchController $nursingController, NhmisDataAggregatorService $aggregator): int
    {
        $this->info('========================================================================');
        $this->info('NHMIS July 2026 Immunization Seeder & Verifier (Routine Antigens 65-87)');
        $this->info('========================================================================');

        // Ensure auth context
        $authUser = User::where('is_admin', '>', 0)->first() ?? User::first();
        if ($authUser) {
            Auth::login($authUser);
            $this->line("Authenticated as: {$authUser->name} (ID: {$authUser->id})");
        }

        if (!$this->option('verify-only')) {
            $this->seedImmunizations($nursingController);
        }

        $this->verifyJuly2026Report($aggregator);

        return Command::SUCCESS;
    }

    /**
     * Seed immunization records using NursingWorkbenchController schedule logic and payloads.
     */
    protected function seedImmunizations(NursingWorkbenchController $nursingController): void
    {
        $this->info("\n--- 1. Ensuring Vaccine Schedule Templates & Items ---");
        $this->ensureScheduleItems();

        $this->info("\n--- 2. Preparing Mother & Baby Patient Cohorts ---");

        // Clean up previous test seed records for July 2026 to ensure repeatable assertions
        $deletedCount = ImmunizationRecord::whereBetween('administered_at', ['2026-07-01 00:00:00', '2026-07-31 23:59:59'])
            ->whereHas('patient', function ($q) {
                $q->where('file_no', 'like', 'NHMIS-VAC-%');
            })
            ->forceDelete();
        if ($deletedCount > 0) {
            $this->comment("Cleaned up {$deletedCount} existing test immunization records in July 2026.");
        }

        // Cohort Definition
        // Baby 1 (<1y): DOB 2026-06-15 (age ~0.5m) -> Birth Doses: BCG, OPV 0, HepB 0 (Fixed Session)
        $baby1 = $this->getOrCreatePatient('NHMIS-VAC-BABY-01', 'BabyOne', 'Aliyu', 'Male', '2026-06-15');

        // Baby 2 (<1y): DOB 2026-05-18 (age ~1.5m / 6w) -> 6-Week Doses: OPV 1, Penta 1, PCV 1, Rota 1 (Fixed Session)
        $baby2 = $this->getOrCreatePatient('NHMIS-VAC-BABY-02', 'BabyTwo', 'Bello', 'Female', '2026-05-18');

        // Baby 3 (<1y): DOB 2026-04-20 (age ~2.5m / 10w) -> 10-Week Doses: OPV 2, Penta 2, PCV 2, Rota 2 (Outreach Session)
        $baby3 = $this->getOrCreatePatient('NHMIS-VAC-BABY-03', 'BabyThree', 'Chukwu', 'Male', '2026-04-20');

        // Baby 4 (<1y): DOB 2026-03-23 (age ~3.5m / 14w) -> 14-Week Doses: OPV 3, Penta 3, PCV 3, Rota 3, IPV (Fixed Session)
        $baby4 = $this->getOrCreatePatient('NHMIS-VAC-BABY-04', 'BabyFour', 'Danjuma', 'Female', '2026-03-23');

        // Baby 5 (<1y): DOB 2025-10-15 (age ~9m) -> 9-Month Doses: Vit A-1, Measles 1, Yellow Fever, Men A, Fully Immunized (Fixed Session)
        $baby5 = $this->getOrCreatePatient('NHMIS-VAC-BABY-05', 'BabyFive', 'Ezekiel', 'Male', '2025-10-15');

        // Child 6 (≥1y): DOB 2025-04-10 (age ~15m) -> 15-Month & Catch-up: Measles 2, OPV 1 (catch-up), Fully Immunized (Outreach Session), BCG (Fixed Session)
        $child6 = $this->getOrCreatePatient('NHMIS-VAC-CHILD-06', 'ChildSix', 'Fashola', 'Female', '2025-04-10');

        // Girl 7 (≥1y, 11 years): DOB 2015-05-10 -> HPV (Fixed Session)
        $girl7 = $this->getOrCreatePatient('NHMIS-VAC-GIRL-07', 'GirlSeven', 'Garba', 'Female', '2015-05-10');

        // Mother 1 (Pregnant woman enrolled in ANC): DOB 1998-03-12 -> Td-1 & Td-2 (Fixed Session)
        $mother1 = $this->getOrCreatePatient('NHMIS-VAC-MOTH-01', 'Amina', 'Ibrahim', 'Female', '1998-03-12');
        $this->ensurePregnantAncEnrollment($mother1);

        // Mother 2 (Non-pregnant woman, age 26): DOB 2000-02-15 -> Td-1 & Td-3 (Fixed Session)
        $mother2 = $this->getOrCreatePatient('NHMIS-VAC-MOTH-02', 'Fatima', 'Jalingo', 'Female', '2000-02-15');

        $this->info("\n--- 3. Administering Vaccines via Nursing Workbench Schedule Module ---");

        // Baby 1 Administrations
        $this->administerViaSchedule($nursingController, $baby1, 1, 'BCG', '2026-07-02 09:30:00', 'ID', 'Right Upper Arm', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby1, 1, 'OPV-0', '2026-07-02 09:35:00', 'Oral', 'Mouth', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby1, 1, 'HBV-0', '2026-07-02 09:40:00', 'IM', 'Left Thigh', 'Fixed session routine immunization');

        // Baby 2 Administrations
        $this->administerViaSchedule($nursingController, $baby2, 1, 'OPV-1', '2026-07-05 10:00:00', 'Oral', 'Mouth', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby2, 1, 'Penta-1', '2026-07-05 10:05:00', 'IM', 'Right Thigh', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby2, 1, 'PCV-1', '2026-07-05 10:10:00', 'IM', 'Left Thigh', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby2, 1, 'Rota-1', '2026-07-05 10:15:00', 'Oral', 'Mouth', 'Fixed session routine immunization');

        // Baby 3 Administrations (Outreach Session)
        $this->administerViaSchedule($nursingController, $baby3, 1, 'OPV-2', '2026-07-10 11:00:00', 'Oral', 'Mouth', 'Outreach session community EPI drive');
        $this->administerViaSchedule($nursingController, $baby3, 1, 'Penta-2', '2026-07-10 11:05:00', 'IM', 'Right Thigh', 'Outreach session community EPI drive');
        $this->administerViaSchedule($nursingController, $baby3, 1, 'PCV-2', '2026-07-10 11:10:00', 'IM', 'Left Thigh', 'Outreach session community EPI drive');
        $this->administerViaSchedule($nursingController, $baby3, 1, 'Rota-2', '2026-07-10 11:15:00', 'Oral', 'Mouth', 'Outreach session community EPI drive');

        // Baby 4 Administrations
        $this->administerViaSchedule($nursingController, $baby4, 1, 'OPV-3', '2026-07-15 09:15:00', 'Oral', 'Mouth', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby4, 1, 'Penta-3', '2026-07-15 09:20:00', 'IM', 'Right Thigh', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby4, 1, 'PCV-3', '2026-07-15 09:25:00', 'IM', 'Left Thigh', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby4, 1, 'Rota-3', '2026-07-15 09:30:00', 'Oral', 'Mouth', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby4, 1, 'IPV', '2026-07-15 09:35:00', 'IM', 'Left Thigh', 'Fixed session routine immunization');

        // Baby 5 Administrations
        $this->administerViaSchedule($nursingController, $baby5, 1, 'Vitamin A-1', '2026-07-20 10:00:00', 'Oral', 'Mouth', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby5, 1, 'Measles-1', '2026-07-20 10:05:00', 'SC', 'Right Upper Arm', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby5, 1, 'Yellow Fever', '2026-07-20 10:10:00', 'SC', 'Left Upper Arm', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby5, 1, 'Men-A', '2026-07-20 10:15:00', 'IM', 'Left Thigh', 'Fixed session routine immunization');
        $this->administerViaSchedule($nursingController, $baby5, 1, 'Fully Immunized', '2026-07-20 10:20:00', 'Oral', 'Mouth', 'Fixed session primary series completed');

        // Child 6 Administrations (≥1y)
        $this->administerViaSchedule($nursingController, $child6, 1, 'Measles-2', '2026-07-22 11:30:00', 'SC', 'Right Upper Arm', 'Outreach session second dose');
        $this->administerViaSchedule($nursingController, $child6, 1, 'OPV-1', '2026-07-22 11:35:00', 'Oral', 'Mouth', 'Outreach session catch-up antigen');
        $this->administerViaSchedule($nursingController, $child6, 1, 'Fully Immunized', '2026-07-22 11:40:00', 'Oral', 'Mouth', 'Outreach session fully immunized completed');
        $this->administerViaSchedule($nursingController, $child6, 1, 'BCG', '2026-07-22 11:45:00', 'ID', 'Right Upper Arm', 'Fixed session catch-up antigen');

        // Girl 7 Administration (HPV, 11 years)
        $this->administerViaSchedule($nursingController, $girl7, 1, 'HPV', '2026-07-25 12:00:00', 'IM', 'Left Deltoid', 'Fixed session adolescent HPV vaccination');

        // Mother 1 Administrations (Pregnant Woman)
        $this->administerViaSchedule($nursingController, $mother1, 2, 'Td-1', '2026-07-08 09:00:00', 'IM', 'Left Deltoid', 'Fixed session pregnant ANC client Td dose');
        $this->administerViaSchedule($nursingController, $mother1, 2, 'Td-2', '2026-07-28 09:00:00', 'IM', 'Left Deltoid', 'Fixed session pregnant ANC client Td dose');

        // Mother 2 Administrations (Non-Pregnant Woman)
        $this->administerViaSchedule($nursingController, $mother2, 2, 'Td-1', '2026-07-12 10:30:00', 'IM', 'Left Deltoid', 'Fixed session non-pregnant woman reproductive age');
        $this->administerViaSchedule($nursingController, $mother2, 2, 'Td-3 (booster)', '2026-07-30 10:30:00', 'IM', 'Left Deltoid', 'Fixed session non-pregnant woman reproductive age');

        $this->info("\nSeeding completed successfully.");
    }

    /**
     * Administer a vaccine schedule item via NursingWorkbenchController::administerFromScheduleNew.
     */
    protected function administerViaSchedule(
        NursingWorkbenchController $controller,
        Patient $patient,
        int $templateId,
        string $doseLabel,
        string $administeredAt,
        string $route,
        string $site,
        string $notes
    ): void {
        // Ensure patient schedule exists
        PatientImmunizationSchedule::generateForPatient($patient->id, $templateId);

        $schedule = PatientImmunizationSchedule::where('patient_id', $patient->id)
            ->whereHas('scheduleItem', function ($q) use ($templateId, $doseLabel) {
                $q->where('template_id', $templateId)->where('dose_label', $doseLabel);
            })
            ->first();

        if (!$schedule) {
            $this->error("Schedule item {$doseLabel} not found for patient {$patient->file_no} (Template {$templateId})");

            return;
        }

        // Build exact Nursing Workbench payload
        $payload = [
            'schedule_id' => $schedule->id,
            'route' => $route,
            'site' => $site,
            'batch_number' => 'VAC-' . Carbon::parse($administeredAt)->format('Ym') . '-' . rand(100, 999),
            'expiry_date' => '2028-12-31',
            'administered_at' => $administeredAt,
            'manufacturer' => 'Serum Institute / UNICEF',
            'notes' => $notes,
        ];

        $request = Request::create('/nursing-workbench/administer-from-schedule', 'POST', $payload);
        $response = $controller->administerFromScheduleNew($request);

        $content = json_decode($response->getContent(), true);

        if ($response->getStatusCode() === 200 && ($content['success'] ?? false)) {
            $this->line(" [OK] Administered {$doseLabel} to {$patient->file_no} at {$administeredAt} (Session: " . (str_contains(strtolower($notes), 'outreach') ? 'Outreach' : 'Fixed') . ')');
        } else {
            $this->error(" [FAIL] Failed administering {$doseLabel} to {$patient->file_no}: " . json_encode($content));
        }
    }

    /**
     * Compile and verify the report for July 2026.
     */
    protected function verifyJuly2026Report(NhmisDataAggregatorService $aggregator): void
    {
        $this->info("\n--- 4. Compiling & Verifying NHMIS Report for July 2026 ---");

        $report = NhmisMonthlyReport::firstOrCreate(
            ['year' => 2026, 'month' => 7],
            [
                'status' => 'draft',
                'created_by' => Auth::id() ?? 1,
                'metadata' => [
                    'rew_microplan_updated' => 1,
                    'ri_fixed_planned' => 4,
                    'ri_fixed_conducted' => 4,
                ],
            ]
        );

        $compiled = $aggregator->compileReport($report);

        $schema = new Nhmis2019Schema();
        $sections = [];
        foreach ($schema->getPages() as $p) {
            foreach ($p['sections'] ?? [] as $sec) {
                $sections[] = $sec;
            }
        }

        // 1. Routine Antigens (Section sec_immunization_antigens, items 65-87)
        $this->info("\n==========================================================================================");
        $this->info("Section: Immunization: Routine Antigens Received (Items 65 - 87)");
        $this->info("==========================================================================================");

        $headers = ['Item #', 'Antigen Label', 'Fixed <1y', 'Outreach <1y', 'Fixed ≥1y', 'Outreach ≥1y', 'Total'];
        $rows = [];

        $antigenSection = null;
        foreach ($sections as $sec) {
            if ($sec['id'] === 'sec_immunization_antigens') {
                $antigenSection = $sec;

                break;
            }
        }

        $allNonZero = true;

        if ($antigenSection) {
            foreach ($antigenSection['rows'] as $r) {
                $rId = $r['id'];
                $num = $r['number'];
                $lbl = $r['label'];

                $fLt = $compiled["{$rId}:fixed_lt_1y"] ?? 0;
                $oLt = $compiled["{$rId}:outreach_lt_1y"] ?? 0;
                $fGe = $compiled["{$rId}:fixed_ge_1y"] ?? 0;
                $oGe = $compiled["{$rId}:outreach_ge_1y"] ?? 0;
                $tot = $compiled["{$rId}:total"] ?? 0;

                if ($tot === 0) {
                    $allNonZero = false;
                }

                $rows[] = [
                    $num,
                    $lbl,
                    $fLt,
                    $oLt,
                    $fGe,
                    $oGe,
                    $tot,
                ];
            }
            $this->table($headers, $rows);
        }

        // 2. Maternal TD (Section sec_immunization_td, items 63-64)
        $this->info("\n==========================================================================================");
        $this->info("Section: Immunization: Tetanus Diphtheria (TD) for Women (Items 63 - 64)");
        $this->info("==========================================================================================");

        $tdHeaders = ['Item #', 'Category', 'TD1', 'TD2', 'TD3', 'TD4', 'TD5', 'Total'];
        $tdRows = [
            [
                63,
                'TD doses given to pregnant women',
                $compiled['row_63:td1'] ?? 0,
                $compiled['row_63:td2'] ?? 0,
                $compiled['row_63:td3'] ?? 0,
                $compiled['row_63:td4'] ?? 0,
                $compiled['row_63:td5'] ?? 0,
                $compiled['row_63:total'] ?? 0,
            ],
            [
                64,
                'TD doses given to non-pregnant women (15-49 years)',
                $compiled['row_64:td1'] ?? 0,
                $compiled['row_64:td2'] ?? 0,
                $compiled['row_64:td3'] ?? 0,
                $compiled['row_64:td4'] ?? 0,
                $compiled['row_64:td5'] ?? 0,
                $compiled['row_64:total'] ?? 0,
            ],
        ];
        $this->table($tdHeaders, $tdRows);

        // Verification Checks
        $this->info("\n--- 5. Verification Assertions Summary ---");
        $checks = [
            'Row 65 (OPV 0, Fixed <1y = 1)' => ($compiled['row_65:fixed_lt_1y'] ?? 0) === 1,
            'Row 66 (HepB 0, Fixed <1y = 1)' => ($compiled['row_66:fixed_lt_1y'] ?? 0) === 1,
            'Row 67 (BCG, Fixed <1y = 1, Fixed ≥1y = 1, Total = 2)' => (($compiled['row_67:fixed_lt_1y'] ?? 0) === 1 && ($compiled['row_67:fixed_ge_1y'] ?? 0) === 1),
            'Row 68 (OPV 1, Fixed <1y = 1, Outreach ≥1y = 1, Total = 2)' => (($compiled['row_68:fixed_lt_1y'] ?? 0) === 1 && ($compiled['row_68:outreach_ge_1y'] ?? 0) === 1),
            'Row 69 (Penta 1, Fixed <1y = 1)' => ($compiled['row_69:fixed_lt_1y'] ?? 0) === 1,
            'Row 70 (PCV 1, Fixed <1y = 1)' => ($compiled['row_70:fixed_lt_1y'] ?? 0) === 1,
            'Row 71 (Rota 1, Fixed <1y = 1)' => ($compiled['row_71:fixed_lt_1y'] ?? 0) === 1,
            'Row 72 (OPV 2, Outreach <1y = 1)' => ($compiled['row_72:outreach_lt_1y'] ?? 0) === 1,
            'Row 73 (Penta 2, Outreach <1y = 1)' => ($compiled['row_73:outreach_lt_1y'] ?? 0) === 1,
            'Row 74 (PCV 2, Outreach <1y = 1)' => ($compiled['row_74:outreach_lt_1y'] ?? 0) === 1,
            'Row 75 (Rota 2, Outreach <1y = 1)' => ($compiled['row_75:outreach_lt_1y'] ?? 0) === 1,
            'Row 76 (OPV 3, Fixed <1y = 1)' => ($compiled['row_76:fixed_lt_1y'] ?? 0) === 1,
            'Row 77 (Penta 3, Fixed <1y = 1)' => ($compiled['row_77:fixed_lt_1y'] ?? 0) === 1,
            'Row 78 (PCV 3, Fixed <1y = 1)' => ($compiled['row_78:fixed_lt_1y'] ?? 0) === 1,
            'Row 79 (Rota 3, Fixed <1y = 1)' => ($compiled['row_79:fixed_lt_1y'] ?? 0) === 1,
            'Row 80 (IPV, Fixed <1y = 1)' => ($compiled['row_80:fixed_lt_1y'] ?? 0) === 1,
            'Row 81 (Vitamin A, Fixed <1y = 1)' => ($compiled['row_81:fixed_lt_1y'] ?? 0) === 1,
            'Row 82 (Measles 1, Fixed <1y = 1)' => ($compiled['row_82:fixed_lt_1y'] ?? 0) === 1,
            'Row 83 (Fully Immunized, Fixed <1y = 1, Outreach ≥1y = 1, Total = 2)' => (($compiled['row_83:fixed_lt_1y'] ?? 0) === 1 && ($compiled['row_83:outreach_ge_1y'] ?? 0) === 1),
            'Row 84 (Yellow Fever, Fixed <1y = 1)' => ($compiled['row_84:fixed_lt_1y'] ?? 0) === 1,
            'Row 85 (Measles 2, Outreach ≥1y = 1)' => ($compiled['row_85:outreach_ge_1y'] ?? 0) === 1,
            'Row 86 (Men A, Fixed <1y = 1)' => ($compiled['row_86:fixed_lt_1y'] ?? 0) === 1,
            'Row 87 (HPV, Fixed ≥1y = 1)' => ($compiled['row_87:fixed_ge_1y'] ?? 0) === 1,
            'Row 63 (Pregnant Td: TD1 = 1, TD2 = 1, Total = 2)' => (($compiled['row_63:td1'] ?? 0) === 1 && ($compiled['row_63:td2'] ?? 0) === 1),
            'Row 64 (Non-pregnant Td: TD1 = 1, TD3 = 1, Total = 2)' => (($compiled['row_64:td1'] ?? 0) === 1 && ($compiled['row_64:td3'] ?? 0) === 1),
        ];

        $passCount = 0;
        foreach ($checks as $title => $passed) {
            if ($passed) {
                $passCount++;
                $this->line(" [PASS] {$title}");
            } else {
                $this->error(" [FAIL] {$title}");
            }
        }

        $this->info("\nVerification Score: {$passCount} / " . count($checks) . ' assertions passed.');
    }

    /**
     * Ensure schedule templates have HPV and Fully Immunized items.
     */
    protected function ensureScheduleItems(): void
    {
        $template1 = VaccineScheduleTemplate::find(1);
        if ($template1) {
            VaccineScheduleItem::firstOrCreate(
                ['template_id' => 1, 'vaccine_code' => 'HPV'],
                [
                    'vaccine_name' => 'HPV',
                    'dose_number' => 1,
                    'dose_label' => 'HPV',
                    'age_days' => 3285,
                    'age_display' => '9-14 Years',
                    'route' => 'IM',
                    'site' => 'Left Deltoid',
                    'notes' => 'Human Papillomavirus vaccine for girls 9-14 years',
                    'sort_order' => 23,
                    'is_required' => false,
                ]
            );

            VaccineScheduleItem::firstOrCreate(
                ['template_id' => 1, 'vaccine_code' => 'FIC'],
                [
                    'vaccine_name' => 'Fully Immunized',
                    'dose_number' => 1,
                    'dose_label' => 'Fully Immunized',
                    'age_days' => 270,
                    'age_display' => '9 Months',
                    'route' => 'Oral',
                    'site' => 'N/A',
                    'notes' => 'Fully Immunized Child completion milestone',
                    'sort_order' => 24,
                    'is_required' => false,
                ]
            );
        }
    }

    /**
     * Create or retrieve patient with specific file number and date of birth.
     */
    protected function getOrCreatePatient(string $fileNo, string $firstName, string $surName, string $gender, string $dob): Patient
    {
        $patient = Patient::where('file_no', $fileNo)->first();
        if ($patient) {
            $patient->update([
                'dob' => $dob,
                'gender' => $gender,
            ]);

            return $patient;
        }

        $user = User::firstOrCreate(
            ['email' => strtolower($fileNo) . '@corehealth.test'],
            [
                'firstname' => $firstName,
                'surname' => $surName,
                'othername' => 'EPI',
                'status' => 1,
                'is_admin' => 19,
                'password' => bcrypt('password123'),
            ]
        );

        return Patient::create([
            'user_id' => $user->id,
            'file_no' => $fileNo,
            'gender' => $gender,
            'dob' => $dob,
            'phone_no' => '080' . rand(10000000, 99999999),
            'address' => 'EPI Catchment Area, CoreHealth Hospital',
            'insurance_scheme' => 1,
        ]);
    }

    /**
     * Ensure a mother patient has an active MaternityEnrollment.
     */
    protected function ensurePregnantAncEnrollment(Patient $patient): void
    {
        MaternityEnrollment::firstOrCreate(
            ['patient_id' => $patient->id],
            [
                'enrolled_by' => Auth::id() ?? 1,
                'enrollment_date' => '2026-06-01',
                'booking_date' => '2026-06-01',
                'entry_point' => 'anc',
                'lmp' => '2026-01-10',
                'edd' => '2026-10-17',
                'gestational_age_at_booking' => 20,
                'gravida' => 2,
                'parity' => 1,
                'status' => 'active',
            ]
        );
    }
}
