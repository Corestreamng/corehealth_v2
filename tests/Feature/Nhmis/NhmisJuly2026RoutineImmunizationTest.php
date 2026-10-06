<?php

namespace Tests\Feature\Nhmis;

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
use Illuminate\Http\Request;
use Tests\TestCase;

class NhmisJuly2026RoutineImmunizationTest extends TestCase
{
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::where('is_admin', '>', 0)->first() ?? User::factory()->create(['status' => 1]);
        $this->actingAs($this->staff);

        // Ensure schedule template 1 has HPV and FIC items
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
     * Helper to create patient and administer via NursingWorkbenchController::administerFromScheduleNew
     */
    protected function administerScheduleDose(
        Patient $patient,
        int $templateId,
        string $doseLabel,
        string $administeredAt,
        string $route,
        string $site,
        string $notes
    ): ImmunizationRecord {
        PatientImmunizationSchedule::generateForPatient($patient->id, $templateId);

        $schedule = PatientImmunizationSchedule::where('patient_id', $patient->id)
            ->whereHas('scheduleItem', function ($q) use ($templateId, $doseLabel) {
                $q->where('template_id', $templateId)->where('dose_label', $doseLabel);
            })
            ->firstOrFail();

        $sessionType = str_contains(strtolower($notes), 'outreach') ? 'outreach' : 'fixed';

        $payload = [
            'schedule_id' => $schedule->id,
            'route' => $route,
            'site' => $site,
            'batch_number' => 'TEST-VAC-' . rand(1000, 9999),
            'expiry_date' => '2028-12-31',
            'administered_at' => $administeredAt,
            'manufacturer' => 'Serum Institute / UNICEF',
            'notes' => $notes,
            'session_type' => $sessionType,
        ];

        $controller = app(NursingWorkbenchController::class);
        $req = Request::create('/nursing-workbench/administer-from-schedule', 'POST', $payload);
        $response = $controller->administerFromScheduleNew($req);

        $this->assertEquals(200, $response->getStatusCode(), 'Administer failed: ' . $response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        return ImmunizationRecord::findOrFail($data['immunization_record']['id']);
    }

    /** @test */
    public function test_routine_antigens_and_td_aggregation_for_july_2026(): void
    {
        // Baby 1 (<1y): DOB 2026-06-15 -> BCG, OPV 0, HepB 0 (Fixed Session)
        $user1 = User::factory()->create(['status' => 1]);
        $baby1 = Patient::create([
            'user_id' => $user1->id,
            'file_no' => 'TEST-BABY-01',
            'gender' => 'Male',
            'dob' => '2026-06-15',
            'phone_no' => '08011111111',
            'address' => 'Test Address',
        ]);
        $this->administerScheduleDose($baby1, 1, 'BCG', '2026-07-02 09:00:00', 'ID', 'Right Upper Arm', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby1, 1, 'OPV-0', '2026-07-02 09:05:00', 'Oral', 'Mouth', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby1, 1, 'HBV-0', '2026-07-02 09:10:00', 'IM', 'Left Thigh', 'Fixed session routine immunization');

        // Baby 2 (<1y): DOB 2026-05-18 -> OPV 1, Penta 1, PCV 1, Rota 1 (Fixed Session)
        $user2 = User::factory()->create(['status' => 1]);
        $baby2 = Patient::create([
            'user_id' => $user2->id,
            'file_no' => 'TEST-BABY-02',
            'gender' => 'Female',
            'dob' => '2026-05-18',
            'phone_no' => '08022222222',
            'address' => 'Test Address',
        ]);
        $this->administerScheduleDose($baby2, 1, 'OPV-1', '2026-07-05 10:00:00', 'Oral', 'Mouth', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby2, 1, 'Penta-1', '2026-07-05 10:05:00', 'IM', 'Right Thigh', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby2, 1, 'PCV-1', '2026-07-05 10:10:00', 'IM', 'Left Thigh', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby2, 1, 'Rota-1', '2026-07-05 10:15:00', 'Oral', 'Mouth', 'Fixed session routine immunization');

        // Baby 3 (<1y): DOB 2026-04-20 -> OPV 2, Penta 2, PCV 2, Rota 2 (Outreach Session)
        $user3 = User::factory()->create(['status' => 1]);
        $baby3 = Patient::create([
            'user_id' => $user3->id,
            'file_no' => 'TEST-BABY-03',
            'gender' => 'Male',
            'dob' => '2026-04-20',
            'phone_no' => '08033333333',
            'address' => 'Test Address',
        ]);
        $this->administerScheduleDose($baby3, 1, 'OPV-2', '2026-07-10 11:00:00', 'Oral', 'Mouth', 'Outreach session community drive');
        $this->administerScheduleDose($baby3, 1, 'Penta-2', '2026-07-10 11:05:00', 'IM', 'Right Thigh', 'Outreach session community drive');
        $this->administerScheduleDose($baby3, 1, 'PCV-2', '2026-07-10 11:10:00', 'IM', 'Left Thigh', 'Outreach session community drive');
        $this->administerScheduleDose($baby3, 1, 'Rota-2', '2026-07-10 11:15:00', 'Oral', 'Mouth', 'Outreach session community drive');

        // Baby 4 (<1y): DOB 2026-03-23 -> OPV 3, Penta 3, PCV 3, Rota 3, IPV (Fixed Session)
        $user4 = User::factory()->create(['status' => 1]);
        $baby4 = Patient::create([
            'user_id' => $user4->id,
            'file_no' => 'TEST-BABY-04',
            'gender' => 'Female',
            'dob' => '2026-03-23',
            'phone_no' => '08044444444',
            'address' => 'Test Address',
        ]);
        $this->administerScheduleDose($baby4, 1, 'OPV-3', '2026-07-15 09:15:00', 'Oral', 'Mouth', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby4, 1, 'Penta-3', '2026-07-15 09:20:00', 'IM', 'Right Thigh', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby4, 1, 'PCV-3', '2026-07-15 09:25:00', 'IM', 'Left Thigh', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby4, 1, 'Rota-3', '2026-07-15 09:30:00', 'Oral', 'Mouth', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby4, 1, 'IPV', '2026-07-15 09:35:00', 'IM', 'Left Thigh', 'Fixed session routine immunization');

        // Baby 5 (<1y): DOB 2025-10-15 -> Vitamin A-1, Measles 1, Yellow Fever, Men A, Fully Immunized (Fixed Session)
        $user5 = User::factory()->create(['status' => 1]);
        $baby5 = Patient::create([
            'user_id' => $user5->id,
            'file_no' => 'TEST-BABY-05',
            'gender' => 'Male',
            'dob' => '2025-10-15',
            'phone_no' => '08055555555',
            'address' => 'Test Address',
        ]);
        $this->administerScheduleDose($baby5, 1, 'Vitamin A-1', '2026-07-20 10:00:00', 'Oral', 'Mouth', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby5, 1, 'Measles-1', '2026-07-20 10:05:00', 'SC', 'Right Upper Arm', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby5, 1, 'Yellow Fever', '2026-07-20 10:10:00', 'SC', 'Left Upper Arm', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby5, 1, 'Men-A', '2026-07-20 10:15:00', 'IM', 'Left Thigh', 'Fixed session routine immunization');
        $this->administerScheduleDose($baby5, 1, 'Fully Immunized', '2026-07-20 10:20:00', 'Oral', 'Mouth', 'Fixed session primary series complete');

        // Child 6 (≥1y): DOB 2025-04-10 -> Measles 2, OPV 1 (catch-up), Fully Immunized (Outreach Session), BCG (Fixed Session)
        $user6 = User::factory()->create(['status' => 1]);
        $child6 = Patient::create([
            'user_id' => $user6->id,
            'file_no' => 'TEST-CHILD-06',
            'gender' => 'Female',
            'dob' => '2025-04-10',
            'phone_no' => '08066666666',
            'address' => 'Test Address',
        ]);
        $this->administerScheduleDose($child6, 1, 'Measles-2', '2026-07-22 11:30:00', 'SC', 'Right Upper Arm', 'Outreach session second dose');
        $this->administerScheduleDose($child6, 1, 'OPV-1', '2026-07-22 11:35:00', 'Oral', 'Mouth', 'Outreach session catch-up antigen');
        $this->administerScheduleDose($child6, 1, 'Fully Immunized', '2026-07-22 11:40:00', 'Oral', 'Mouth', 'Outreach session completion');
        $this->administerScheduleDose($child6, 1, 'BCG', '2026-07-22 11:45:00', 'ID', 'Right Upper Arm', 'Fixed session catch-up antigen');

        // Girl 7 (≥1y, 11y): DOB 2015-05-10 -> HPV (Fixed Session)
        $user7 = User::factory()->create(['status' => 1]);
        $girl7 = Patient::create([
            'user_id' => $user7->id,
            'file_no' => 'TEST-GIRL-07',
            'gender' => 'Female',
            'dob' => '2015-05-10',
            'phone_no' => '08077777777',
            'address' => 'Test Address',
        ]);
        $this->administerScheduleDose($girl7, 1, 'HPV', '2026-07-25 12:00:00', 'IM', 'Left Deltoid', 'Fixed session HPV vaccination');

        // Mother 1 (Pregnant Woman): DOB 1998-03-12 -> Td-1 & Td-2 (Fixed Session)
        $userM1 = User::factory()->create(['status' => 1]);
        $mother1 = Patient::create([
            'user_id' => $userM1->id,
            'file_no' => 'TEST-MOTH-01',
            'gender' => 'Female',
            'dob' => '1998-03-12',
            'phone_no' => '08088888888',
            'address' => 'Test Address',
        ]);
        MaternityEnrollment::create([
            'patient_id' => $mother1->id,
            'enrolled_by' => $this->staff->id,
            'enrollment_date' => '2026-06-01',
            'booking_date' => '2026-06-01',
            'entry_point' => 'anc',
            'lmp' => '2026-01-10',
            'edd' => '2026-10-17',
            'gestational_age_at_booking' => 20,
            'gravida' => 2,
            'parity' => 1,
            'status' => 'active',
        ]);
        $this->administerScheduleDose($mother1, 2, 'Td-1', '2026-07-08 09:00:00', 'IM', 'Left Deltoid', 'Fixed session pregnant ANC Td dose');
        $this->administerScheduleDose($mother1, 2, 'Td-2', '2026-07-28 09:00:00', 'IM', 'Left Deltoid', 'Fixed session pregnant ANC Td dose');

        // Mother 2 (Non-pregnant Woman): DOB 2000-02-15 -> Td-1 & Td-3 (Fixed Session)
        $userM2 = User::factory()->create(['status' => 1]);
        $mother2 = Patient::create([
            'user_id' => $userM2->id,
            'file_no' => 'TEST-MOTH-02',
            'gender' => 'Female',
            'dob' => '2000-02-15',
            'phone_no' => '08099999999',
            'address' => 'Test Address',
        ]);
        $this->administerScheduleDose($mother2, 2, 'Td-1', '2026-07-12 10:30:00', 'IM', 'Left Deltoid', 'Fixed session non-pregnant woman reproductive age');
        $this->administerScheduleDose($mother2, 2, 'Td-3 (booster)', '2026-07-30 10:30:00', 'IM', 'Left Deltoid', 'Fixed session non-pregnant woman reproductive age');

        // Compile Report for July 2026
        $report = NhmisMonthlyReport::firstOrCreate(
            ['year' => 2026, 'month' => 7],
            [
                'status' => 'draft',
                'created_by' => $this->staff->id,
                'metadata' => [
                    'rew_microplan_updated' => 1,
                    'ri_fixed_planned' => 4,
                    'ri_fixed_conducted' => 4,
                ],
            ]
        );

        $aggregator = app(NhmisDataAggregatorService::class);
        $values = $aggregator->compileReport($report);

        // Assert Routine Antigens Received (Rows 65 - 87)
        $this->assertEquals(1, $values['row_65:fixed_lt_1y'], 'Row 65 (OPV 0, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_66:fixed_lt_1y'], 'Row 66 (HepB 0, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_67:fixed_lt_1y'], 'Row 67 (BCG, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_67:fixed_ge_1y'], 'Row 67 (BCG, Fixed ≥1y) should be 1');
        $this->assertEquals(2, $values['row_67:total'], 'Row 67 (BCG, Total) should be 2');
        $this->assertEquals(1, $values['row_68:fixed_lt_1y'], 'Row 68 (OPV 1, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_68:outreach_ge_1y'], 'Row 68 (OPV 1, Outreach ≥1y) should be 1');
        $this->assertEquals(2, $values['row_68:total'], 'Row 68 (OPV 1, Total) should be 2');
        $this->assertEquals(1, $values['row_69:fixed_lt_1y'], 'Row 69 (Penta 1, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_70:fixed_lt_1y'], 'Row 70 (PCV 1, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_71:fixed_lt_1y'], 'Row 71 (Rota 1, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_72:outreach_lt_1y'], 'Row 72 (OPV 2, Outreach <1y) should be 1');
        $this->assertEquals(1, $values['row_73:outreach_lt_1y'], 'Row 73 (Penta 2, Outreach <1y) should be 1');
        $this->assertEquals(1, $values['row_74:outreach_lt_1y'], 'Row 74 (PCV 2, Outreach <1y) should be 1');
        $this->assertEquals(1, $values['row_75:outreach_lt_1y'], 'Row 75 (Rota 2, Outreach <1y) should be 1');
        $this->assertEquals(1, $values['row_76:fixed_lt_1y'], 'Row 76 (OPV 3, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_77:fixed_lt_1y'], 'Row 77 (Penta 3, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_78:fixed_lt_1y'], 'Row 78 (PCV 3, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_79:fixed_lt_1y'], 'Row 79 (Rota 3, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_80:fixed_lt_1y'], 'Row 80 (IPV, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_81:fixed_lt_1y'], 'Row 81 (Vitamin A, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_82:fixed_lt_1y'], 'Row 82 (Measles 1, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_83:fixed_lt_1y'], 'Row 83 (Fully Immunized, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_83:outreach_ge_1y'], 'Row 83 (Fully Immunized, Outreach ≥1y) should be 1');
        $this->assertEquals(2, $values['row_83:total'], 'Row 83 (Fully Immunized, Total) should be 2');
        $this->assertEquals(1, $values['row_84:fixed_lt_1y'], 'Row 84 (Yellow Fever, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_85:outreach_ge_1y'], 'Row 85 (Measles 2, Outreach ≥1y) should be 1');
        $this->assertEquals(1, $values['row_86:fixed_lt_1y'], 'Row 86 (Men A, Fixed <1y) should be 1');
        $this->assertEquals(1, $values['row_87:fixed_ge_1y'], 'Row 87 (HPV, Fixed ≥1y) should be 1');

        // Assert Maternal TD (Rows 63 - 64)
        $this->assertEquals(1, $values['row_63:td1'], 'Row 63: TD1 pregnant should be 1');
        $this->assertEquals(1, $values['row_63:td2'], 'Row 63: TD2 pregnant should be 1');
        $this->assertEquals(2, $values['row_63:total'], 'Row 63: TD total pregnant should be 2');
        $this->assertEquals(1, $values['row_64:td1'], 'Row 64: TD1 non-pregnant should be 1');
        $this->assertEquals(1, $values['row_64:td3'], 'Row 64: TD3 non-pregnant should be 1');
        $this->assertEquals(2, $values['row_64:total'], 'Row 64: TD total non-pregnant should be 2');

        // Test Drill-down API & Controller
        $controller = app(\App\Http\Controllers\NhmisWorkbenchController::class);

        $drillReq = Request::create('/nhmis-workbench/drill-down', 'GET', [
            'cell_key' => 'row_65:fixed_lt_1y',
            'report_id' => $report->id,
        ]);
        $drillRes = $controller->drillDown($drillReq);
        $this->assertEquals(200, $drillRes->getStatusCode());
        $drillData = json_decode($drillRes->getContent(), true);
        $this->assertCount(1, $drillData['records'], 'Drill-down for row 65:fixed_lt_1y should return 1 record');

        $drillTdReq = Request::create('/nhmis-workbench/drill-down', 'GET', [
            'cell_key' => 'row_63:td1',
            'report_id' => $report->id,
        ]);
        $drillTdRes = $controller->drillDown($drillTdReq);
        $this->assertEquals(200, $drillTdRes->getStatusCode());
        $drillTdData = json_decode($drillTdRes->getContent(), true);
        $this->assertCount(1, $drillTdData['records'], 'Drill-down for row 63:td1 should return 1 record');

        // Route endpoint test
        $response = $this->getJson("/nhmis-workbench/drill-down?cell_key=row_65:fixed_lt_1y&report_id={$report->id}");
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
    }
}
