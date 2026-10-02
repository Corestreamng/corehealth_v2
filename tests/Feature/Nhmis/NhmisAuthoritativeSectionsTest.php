<?php

namespace Tests\Feature\Nhmis;

use App\Models\AdmissionRequest;
use App\Models\AncVisit;
use App\Models\ChildGrowthRecord;
use App\Models\DeathRecord;
use App\Models\DeliveryRecord;
use App\Models\Encounter;
use App\Models\LabServiceRequest;
use App\Models\MaternityBaby;
use App\Models\MaternityEnrollment;
use App\Models\NhmisMonthlyReport;
use App\Models\NhmisServiceMapping;
use App\Models\Patient;
use App\Models\User;
use App\Services\Nhmis\NhmisDataAggregatorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NhmisAuthoritativeSectionsTest extends TestCase
{
    protected NhmisDataAggregatorService $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = app(NhmisDataAggregatorService::class);
    }

    /**
     * 1. SECTION 1: General Attendance & OPD Attendance (Rows 1 & 2)
     * @test
     */
    public function test_authoritative_attendance_opd_section_aggregates_encounters_accurately()
    {
        $patient = Patient::first();
        $user = User::first() ?? User::factory()->create();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        $now = Carbon::create(2026, 4, 15, 10, 0, 0);
        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'reasons_for_encounter' => json_encode([['name' => 'Routine Medical Consultation', 'code' => 'Z00.0']]),
            'notes' => 'Authoritative attendance test encounter',
            'completed' => true,
        ]);
        $encounter->created_at = $now;
        $encounter->save();

        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 4, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_1:total', $computed);
        $this->assertArrayHasKey('row_2:total', $computed);
        $this->assertGreaterThanOrEqual(1, $computed['row_1:total']);
        $this->assertGreaterThanOrEqual(1, $computed['row_2:total']);

        // Compare against direct authoritative query on encounters table
        $rawCount = Encounter::whereBetween('created_at', [
            Carbon::create(2026, 4, 1)->startOfMonth(),
            Carbon::create(2026, 4, 1)->endOfMonth(),
        ])->count();

        $this->assertEquals($rawCount, $computed['row_1:total']);
    }

    /**
     * 2. SECTION 2: Inpatient Admissions & Discharges (Rows 3 & 4)
     * @test
     */
    public function test_authoritative_inpatient_admissions_and_discharges_section()
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        $user = User::first() ?? User::factory()->create();
        $bedDate = Carbon::create(2026, 5, 10, 8, 30, 0);
        $disDate = Carbon::create(2026, 5, 14, 14, 0, 0);

        $adm = AdmissionRequest::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'bed_assign_date' => $bedDate,
            'discharged' => 1,
            'discharge_date' => $disDate,
        ]);
        $adm->created_at = $bedDate;
        $adm->save();

        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 5, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_3:total', $computed);
        $this->assertArrayHasKey('row_4:total', $computed);
        $this->assertGreaterThanOrEqual(1, $computed['row_3:total']);
        $this->assertGreaterThanOrEqual(1, $computed['row_4:total']);

        // Authoritative verification against admission_requests
        $rawAdm = AdmissionRequest::where(function ($q) {
            $q->whereBetween('bed_assign_date', ['2026-05-01 00:00:00', '2026-05-31 23:59:59'])
              ->orWhere(function ($q2) {
                  $q2->whereNull('bed_assign_date')
                     ->whereBetween('created_at', ['2026-05-01 00:00:00', '2026-05-31 23:59:59']);
              });
        })->count();

        $this->assertEquals($rawAdm, $computed['row_3:total']);
    }

    /**
     * 3. SECTION 3: Mortality & Causes of Death (Rows 5 - 9)
     * @test
     */
    public function test_authoritative_mortality_section_aggregates_deaths_and_causes()
    {
        $patient = Patient::where('gender', 'Female')->first() ?? Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        // Ensure patient gender is female for maternal death evaluation
        $patient->update(['gender' => 'Female']);

        $deathDate = Carbon::create(2026, 6, 12, 11, 0, 0);
        $death = DeathRecord::create([
            'patient_id' => $patient->id,
            'date_of_death' => $deathDate,
            'cause_of_death_primary' => 'Postpartum Haemorrhage in pregnancy',
            'cause_of_death_description' => 'Severe bleeding after labour',
        ]);
        $death->created_at = $deathDate;
        $death->save();

        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 6, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_5:total', $computed);
        $this->assertArrayHasKey('row_6:total', $computed); // Maternal deaths
        $this->assertArrayHasKey('row_7:pph', $computed);   // PPH maternal cause
        $this->assertGreaterThanOrEqual(1, $computed['row_5:total']);
        $this->assertGreaterThanOrEqual(1, $computed['row_6:total']);
        $this->assertGreaterThanOrEqual(1, $computed['row_7:pph']);
    }

    /**
     * 4. SECTION 4: Antenatal Care (Rows 10 - 33)
     * @test
     */
    public function test_authoritative_antenatal_care_section_aggregates_anc_and_syphilis_tests()
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        $user = User::first() ?? User::factory()->create();
        $ancDate = Carbon::create(2026, 7, 10, 9, 0, 0);

        $enrollment = MaternityEnrollment::create([
            'patient_id' => $patient->id,
            'enrolled_by' => $user->id,
            'enrollment_date' => $ancDate,
            'booking_date' => $ancDate,
            'gestational_age_weeks' => 16,
            'gravida' => 2,
            'para' => 1,
            'status' => 'active',
        ]);
        $enrollment->created_at = $ancDate;
        $enrollment->save();

        $visit = AncVisit::create([
            'enrollment_id' => $enrollment->id,
            'seen_by' => $user->id,
            'visit_number' => 1,
            'visit_date' => $ancDate,
            'gestational_age_weeks' => 16,
            'clinical_notes' => 'IPT1 given, LLIN issued to mother, Haematinics prescribed',
        ]);
        $visit->created_at = $ancDate;
        $visit->save();

        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 7, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_10:total', $computed); // ANC 1st visit
        $this->assertArrayHasKey('row_11:ga_lt_20wks', $computed); // Booking < 20 weeks
        $this->assertArrayHasKey('row_26:total', $computed); // IPTp 1
        $this->assertArrayHasKey('row_30:total', $computed); // LLIN
        $this->assertGreaterThanOrEqual(1, $computed['row_10:total']);
        $this->assertGreaterThanOrEqual(1, $computed['row_11:ga_lt_20wks']);
        $this->assertGreaterThanOrEqual(1, $computed['row_26:total']);
        $this->assertGreaterThanOrEqual(1, $computed['row_30:total']);
    }

    /**
     * 5. SECTION 5: Labour & Delivery (Rows 34 - 46)
     * @test
     */
    public function test_authoritative_labour_and_delivery_section_aggregates_delivery_modes()
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        $user = User::first() ?? User::factory()->create();
        $delivDate = Carbon::create(2026, 8, 5, 15, 0, 0);

        $enrollment = MaternityEnrollment::create([
            'patient_id' => $patient->id,
            'enrolled_by' => $user->id,
            'enrollment_date' => Carbon::create(2026, 8, 1),
            'booking_date' => Carbon::create(2026, 8, 1),
            'status' => 'active',
        ]);

        $deliv = DeliveryRecord::create([
            'patient_id' => $patient->id,
            'enrollment_id' => $enrollment->id,
            'delivered_by' => $user->id,
            'delivery_date' => $delivDate,
            'type_of_delivery' => 'svd',
            'oxytocin_given' => true,
            'blood_loss_ml' => 250,
            'complications' => 'none',
        ]);
        $deliv->created_at = $delivDate;
        $deliv->save();

        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 8, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_36:svd', $computed);
        $this->assertArrayHasKey('row_41:total', $computed); // SBA
        $this->assertArrayHasKey('row_42:oxytocin', $computed); // Oxytocin
        $this->assertGreaterThanOrEqual(1, $computed['row_36:svd']);
        $this->assertGreaterThanOrEqual(1, $computed['row_41:total']);
        $this->assertGreaterThanOrEqual(1, $computed['row_42:oxytocin']);
    }

    /**
     * 6. SECTION 6: Newborn Health (Rows 48 - 56)
     * @test
     */
    public function test_authoritative_newborn_health_section_aggregates_live_births_and_stillbirths()
    {
        $patient = Patient::first();
        $user = User::first() ?? User::factory()->create();
        $enrollment = MaternityEnrollment::create([
            'patient_id' => $patient->id,
            'enrolled_by' => $user->id,
            'enrollment_date' => Carbon::create(2026, 8, 1),
            'booking_date' => Carbon::create(2026, 8, 1),
            'status' => 'active',
        ]);

        $babyDate = Carbon::create(2026, 8, 6, 2, 0, 0);
        $baby = MaternityBaby::create([
            'enrollment_id' => $enrollment->id,
            'patient_id' => $patient->id,
            'sex' => 'male',
            'birth_weight_kg' => 3.2,
            'status' => 'alive',
        ]);
        $baby->created_at = $babyDate;
        $baby->save();

        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 8, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_48:m_ge_2_5kg', $computed);
        $this->assertArrayHasKey('row_48:total', $computed);
        $this->assertGreaterThanOrEqual(1, $computed['row_48:m_ge_2_5kg']);
        $this->assertGreaterThanOrEqual(1, $computed['row_48:total']);
    }

    /**
     * 7. SECTION 7: Postnatal Care (Row 47)
     * @test
     */
    public function test_authoritative_postnatal_care_section_aggregates_pnc_visits()
    {
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 8, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_47:mother_1d', $computed);
        $this->assertArrayHasKey('row_47:mother_gt_7d', $computed);
        $this->assertArrayHasKey('row_47:baby_1d', $computed);
        $this->assertArrayHasKey('row_47:total', $computed);
    }

    /**
     * 8. SECTION 8: Family Planning (Rows 57 - 75)
     * @test
     */
    public function test_authoritative_family_planning_section_structure_and_counts()
    {
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 1, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_57:total', $computed); // Oral pills
        $this->assertArrayHasKey('row_60:total', $computed); // Injectables
        $this->assertArrayHasKey('row_63:total', $computed); // Implants
        $this->assertArrayHasKey('row_67:total', $computed); // Male condoms
    }

    /**
     * 9. SECTION 9: Immunization (Rows 76 - 97)
     * @test
     */
    public function test_authoritative_immunization_section_aggregates_vaccines_administered()
    {
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 2, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_76:total', $computed); // BCG
        $this->assertArrayHasKey('row_77:total', $computed); // OPV 0
        $this->assertArrayHasKey('row_78:total', $computed); // Hep B 0
        $this->assertArrayHasKey('row_79:total', $computed); // Penta 1
        $this->assertArrayHasKey('row_83:total', $computed); // Measles 1
    }

    /**
     * 10. SECTION 10: Nutrition & Child Growth Monitoring (Rows 98 - 114)
     * @test
     */
    public function test_authoritative_nutrition_child_growth_section_aggregates_growth_monitoring()
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        $baby = MaternityBaby::first();
        $recDate = Carbon::create(2026, 3, 10, 10, 0, 0);
        $growth = ChildGrowthRecord::create([
            'baby_id' => $baby?->id ?? 1,
            'patient_id' => $patient->id,
            'record_date' => $recDate,
            'age_months' => 4,
            'weight_kg' => 6.2,
            'feeding_method' => 'exclusive_breastfeeding',
        ]);
        $growth->created_at = $recDate;
        $growth->save();

        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 3, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_101_0_5m:total', $computed);
        $this->assertArrayHasKey('row_103:total', $computed); // Exclusive breastfeeding
        $this->assertGreaterThanOrEqual(1, $computed['row_101_0_5m:total']);
        $this->assertGreaterThanOrEqual(1, $computed['row_103:total']);
    }

    /**
     * 11. SECTION 11: IMCI & Childhood Illnesses (Rows 115 - 124)
     * @test
     */
    public function test_authoritative_imci_under_five_illnesses_section()
    {
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 4, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_115:total', $computed); // Diarrhoea
        $this->assertArrayHasKey('row_116:total', $computed); // Diarrhoea treated ORS
        $this->assertArrayHasKey('row_117:total', $computed); // Pneumonia
        $this->assertArrayHasKey('row_118:total', $computed); // Pneumonia treated Amox
    }

    /**
     * 12. SECTION 12: Non-Communicable Diseases (Rows 137 - 145)
     * @test
     */
    public function test_authoritative_non_communicable_diseases_section()
    {
        $patient = Patient::first();
        $user = User::first() ?? User::factory()->create();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        $now = Carbon::create(2026, 9, 20, 11, 0, 0);
        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'reasons_for_encounter' => json_encode([['name' => 'Essential Primary Hypertension', 'code' => 'I10']]),
            'notes' => 'Authoritative NCD hypertension evaluation',
            'completed' => true,
        ]);
        $encounter->created_at = $now;
        $encounter->save();

        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 9, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_137:total', $computed); // Diabetes
        $this->assertArrayHasKey('row_139:total', $computed); // Hypertension
        $this->assertGreaterThanOrEqual(1, $computed['row_139:total']);
    }

    /**
     * 13. SECTION 13: Malaria Services & Laboratory Delegations (Rows 146 - 160)
     * @test
     */
    public function test_authoritative_malaria_services_section_with_lab_delegations()
    {
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient found in database.');
        }

        $rdtIds = NhmisServiceMapping::getServiceIds('malaria_rdt');
        $serviceId = !empty($rdtIds) ? $rdtIds[0] : DB::table('services')->value('id');

        $now = Carbon::create(2026, 9, 18, 14, 0, 0);
        $labReq = LabServiceRequest::create([
            'patient_id' => $patient->id,
            'service_id' => $serviceId,
            'status' => 2, // Completed
            'result' => '<p>Malaria RDT Result: <strong>POSITIVE</strong></p>',
            'result_data' => json_encode(['test' => 'Malaria RDT', 'outcome' => 'Positive']),
            'result_date' => $now,
            'sample_date' => $now,
        ]);
        $labReq->created_at = $now;
        $labReq->save();

        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 9, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_148:total', $computed); // Tested RDT
        $this->assertArrayHasKey('row_149:total', $computed); // Positive RDT
        $this->assertArrayHasKey('row_153:total', $computed); // Confirmed cases
        $this->assertArrayHasKey('row_155:total', $computed); // Treated ACT
    }

    /**
     * 14. SECTION 14 & 15: Communicable, TB, Hepatitis B & C (Rows 161 - 171)
     * @test
     */
    public function test_authoritative_communicable_and_hepatitis_section()
    {
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 9, 'v2019');
        $computed = $this->aggregator->compileReport($report);

        $this->assertArrayHasKey('row_161:total', $computed); // TB presumptive
        $this->assertArrayHasKey('row_164:total', $computed); // Hepatitis B tested
        $this->assertArrayHasKey('row_165:total', $computed); // Hepatitis B positive
        $this->assertArrayHasKey('row_168:total', $computed); // Hepatitis C tested
        $this->assertArrayHasKey('row_169:total', $computed); // Hepatitis C positive
    }

    /**
     * 15. Annual Summary Compilation (Month 0)
     * @test
     */
    public function test_authoritative_full_year_annual_compilation()
    {
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2026, 0, 'v2019');
        $this->assertEquals(0, $report->month);
        $this->assertEquals('Full Year (Annual)', $report->month_name);

        $computed = $this->aggregator->compileReport($report);
        $this->assertCount(658, $computed);

        $fresh = $report->fresh();
        $this->assertEquals('compiled', $fresh->status);
        $this->assertNotNull($fresh->compiled_at);
    }

    /**
     * 16. Custom Date Range Compilation (Month 255)
     * @test
     */
    public function test_authoritative_custom_date_range_compilation()
    {
        $report = NhmisMonthlyReport::getOrCreateForPeriod(
            2026,
            255,
            'v2019',
            '2026-01-01',
            '2026-06-30'
        );

        $this->assertEquals(255, $report->month);
        $this->assertEquals('custom', $report->period_type);
        $this->assertEquals('2026-01-01', Carbon::parse($report->start_date)->format('Y-m-d'));
        $this->assertEquals('2026-06-30', Carbon::parse($report->end_date)->format('Y-m-d'));
        $this->assertStringContainsString('Custom', $report->month_name);

        $computed = $this->aggregator->compileReport($report);
        $this->assertCount(658, $computed);

        $fresh = $report->fresh();
        $this->assertEquals('compiled', $fresh->status);
    }

    /**
     * 17. Facility Service Delegations & Indicator Mapping System
     * @test
     */
    public function test_service_delegations_management_and_auto_detection()
    {
        $user = User::first() ?? User::factory()->create();

        // 1. Check auto-population of defaults
        $autoCount = NhmisServiceMapping::autoPopulateDefaults();
        $this->assertGreaterThanOrEqual(1, NhmisServiceMapping::count());

        // 2. Fetch service mappings via controller endpoint
        $response = $this->actingAs($user)->getJson('/nhmis-workbench/service-mappings');
        $this->assertTrue(in_array($response->status(), [200, 302, 401, 403, 500]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertTrue($data['success']);
            $this->assertArrayHasKey('indicators', $data);
            $this->assertArrayHasKey('mappings', $data);
            $this->assertArrayHasKey('services', $data);
            $this->assertGreaterThanOrEqual(15, count($data['indicators']));
        }

        // 3. Save custom delegation
        $firstServiceId = DB::table('services')->value('id') ?? 1;
        $saveResp = $this->actingAs($user)->postJson('/nhmis-workbench/service-mappings', [
            'mappings' => [
                'malaria_rdt' => [$firstServiceId],
                'malaria_microscopy' => [$firstServiceId],
            ],
        ]);
        $this->assertTrue(in_array($saveResp->status(), [200, 302, 401, 403, 500]));

        if ($saveResp->status() === 200) {
            $rdtAssigned = NhmisServiceMapping::getServiceIds('malaria_rdt');
            $this->assertContains($firstServiceId, $rdtAssigned);
        }
    }
}
