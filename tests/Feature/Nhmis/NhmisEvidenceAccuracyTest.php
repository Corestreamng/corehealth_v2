<?php

namespace Tests\Feature\Nhmis;

use App\Http\Controllers\NhmisWorkbenchController;
use App\Models\NhmisMonthlyReport;
use App\Models\NhmisServiceMapping;
use App\Models\Patient;
use App\Models\User;
use App\Services\Nhmis\NhmisDataAggregatorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Each NHMIS number must be backed by real EMR records (its Drill Down list), and rows the EMR
 * cannot compute must stay blank. Fixtures use February 2030 so the period holds only test data.
 */
class NhmisEvidenceAccuracyTest extends TestCase
{
    protected NhmisDataAggregatorService $aggregator;

    protected User $doctor;

    protected Carbon $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = app(NhmisDataAggregatorService::class);
        $this->doctor = User::first() ?? User::factory()->create();
        $this->day = Carbon::create(2030, 2, 12, 10, 0, 0);
    }

    protected function patient(array $attrs = []): Patient
    {
        return Patient::factory()->create(array_merge(['dob' => '1990-05-01', 'gender' => 'Female'], $attrs));
    }

    protected function encounter(Patient $p, string $notes, $reasons = null, ?Carbon $at = null): int
    {
        $at ??= $this->day;

        return DB::table('encounters')->insertGetId([
            'patient_id' => $p->id,
            'doctor_id' => $this->doctor->id,
            'notes' => $notes,
            'reasons_for_encounter' => $reasons ? json_encode($reasons) : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    protected function build(): array
    {
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2030, 2, 'v2019');

        return $this->aggregator->build($report);
    }

    /** @test */
    public function test_every_cell_equals_the_records_behind_it_and_manual_cells_are_blank()
    {
        $p = $this->patient();
        $this->encounter($p, 'ASS; SIMPLE MALARIA PLAN; TAB ACT');

        $result = $this->build();
        $this->assertCount(661, $result['values']);

        foreach ($result['values'] as $key => $value) {
            if ($value === null) {
                $this->assertArrayHasKey($key, $result['manual'], "{$key} is blank but has no manual reason");

                continue;
            }
            $sum = array_sum(array_map(fn ($e) => $e['qty'] ?? 1, $result['evidence'][$key] ?? []));
            $this->assertEquals($sum, $value, "{$key} does not equal the records behind it");
        }

        foreach (['row_34:total', 'row_91:val', 'row_92:planned', 'row_97:val', 'row_98:total'] as $manualKey) {
            $this->assertNull($result['values'][$manualKey], "{$manualKey} must not be invented");
        }
    }

    /** @test */
    public function test_drill_down_returns_exactly_the_counted_records()
    {
        $p = $this->patient();
        $this->encounter($p, 'Review. ASS; Simple malaria. Plan: ACT');
        $report = NhmisMonthlyReport::getOrCreateForPeriod(2030, 2, 'v2019');
        $values = $this->aggregator->compileReport($report);

        $controller = app(NhmisWorkbenchController::class);
        foreach (['row_1:total', 'row_152:total', 'row_152:ge_5y_excl_pw'] as $cell) {
            $res = $controller->drillDown(new Request(['cell_key' => $cell, 'report_id' => $report->id, 'per_page' => 100]))->getData(true);
            $this->assertEquals($values[$cell], $res['total_records'], "Drill down for {$cell}");
        }
    }

    /** @test */
    public function test_negated_and_queried_diagnoses_are_not_counted_but_custom_ones_are()
    {
        $negated = $this->patient();
        $this->encounter($negated, 'C/O headache. NO FEVER, no cough. ASS; tension headache PLAN; PCM');
        $queried = $this->patient();
        $this->encounter($queried, 'Fever x 3/7. ASS; ? MALARIA PLAN; DO MP');
        $custom = $this->patient();
        $this->encounter($custom, 'Seen', [['value' => 'CUSTOM-MALARIA ..', 'display' => 'CUSTOM - MALARIA ..', 'code' => 'CUSTOM', 'name' => 'MALARIA ..', 'comment_1' => 'CONFIRMED', 'comment_2' => 'NA']]);
        $synonym = $this->patient();
        $this->encounter($synonym, 'Ass: Plasmodiasis. Plan: Coartem');

        $r = $this->build();
        $clinical = array_column($r['evidence']['row_152:total'] ?? [], 'patient_id');
        $fever = array_column($r['evidence']['row_147:total'] ?? [], 'patient_id');

        $this->assertNotContains($negated->id, $fever, '"NO FEVER" must not count as fever');
        $this->assertContains($queried->id, $fever);
        $this->assertNotContains($queried->id, $clinical, '"? malaria" is a query, not a diagnosis');
        $this->assertContains($custom->id, $clinical, 'Custom (non-ICD) diagnoses must be counted');
        $this->assertContains($synonym->id, $clinical, 'Plasmodiasis is malaria');
    }

    /** @test */
    public function test_patients_without_dob_count_in_total_only_never_as_adults()
    {
        $noDob = $this->patient(['dob' => null, 'gender' => 'Male']);
        $this->encounter($noDob, 'Routine review');

        $r = $this->build();
        $this->assertEquals(1, $r['values']['row_1:total']);
        $this->assertEquals(0, $r['values']['row_1:m_ge_20y']);
        $this->assertEquals(1, $r['unplaced']['row_1']);
    }

    /** @test */
    public function test_still_births_are_not_deaths_and_cs_types_and_nil_complications_are_read_correctly()
    {
        $mother = $this->patient();
        DB::table('death_records')->insert(['patient_id' => $mother->id, 'date_of_death' => $this->day, 'death_type' => 'Still Birth', 'created_at' => $this->day, 'updated_at' => $this->day]);

        $enrol = DB::table('maternity_enrollments')->insertGetId(['patient_id' => $mother->id, 'enrolled_by' => $this->doctor->id, 'enrollment_date' => $this->day, 'status' => 'completed', 'lmp' => '2029-05-10', 'edd' => '2030-02-14', 'created_at' => $this->day, 'updated_at' => $this->day]);
        foreach ([['elective_cs', '<p>NIL</p>'], ['emergency_cs', '<p>nill&nbsp;</p>'], ['svd', '<p>ABRUPTIO PLACENTA</p>']] as [$type, $comp]) {
            DB::table('delivery_records')->insert(['enrollment_id' => $enrol, 'patient_id' => $mother->id, 'type_of_delivery' => $type, 'delivered_by' => $this->doctor->id, 'delivery_date' => $this->day, 'complications' => $comp, 'created_at' => $this->day, 'updated_at' => $this->day]);
        }

        $r = $this->build();
        $this->assertEquals(0, $r['values']['row_5:total'], 'A still birth is not a death in row 5');
        $this->assertEquals(1, $r['values']['row_50:total']);
        $this->assertEquals(2, $r['values']['row_36:c_section']);
        $this->assertEquals(1, $r['values']['row_36:svd']);
        $this->assertEquals(1, $r['values']['row_38:total'], '"NIL" is not a complication');
    }

    /** @test */
    public function test_only_external_referrals_and_resulted_linked_lab_tests_are_counted()
    {
        $p = $this->patient();
        $enc = $this->encounter($p, 'Seen');
        foreach (['internal', 'external'] as $type) {
            DB::table('specialist_referrals')->insert(['patient_id' => $p->id, 'encounter_id' => $enc, 'referring_doctor_id' => $this->doctor->id, 'referring_clinic_id' => 1, 'reason' => 'Malaria not responding', 'provisional_diagnosis' => 'Severe malaria', 'referral_type' => $type, 'created_at' => $this->day, 'updated_at' => $this->day]);
        }

        $service = DB::table('services')->insertGetId(['user_id' => $this->doctor->id, 'category_id' => 2, 'service_name' => 'MP Accuracy Test ' . uniqid(), 'status' => 1, 'created_at' => $this->day, 'updated_at' => $this->day]);
        NhmisServiceMapping::create(['indicator_code' => 'malaria_microscopy', 'service_id' => $service, 'service_type' => 'investigation']);
        $tested = $this->patient();
        $pending = $this->patient();
        DB::table('lab_service_requests')->insert(['patient_id' => $tested->id, 'service_id' => $service, 'result' => '<p>MP: Seen (++)</p>', 'sample_date' => $this->day, 'created_at' => $this->day, 'updated_at' => $this->day]);
        DB::table('lab_service_requests')->insert(['patient_id' => $pending->id, 'service_id' => $service, 'result' => null, 'sample_date' => $this->day, 'created_at' => $this->day, 'updated_at' => $this->day]);

        $r = $this->build();
        $this->assertEquals(1, $r['values']['row_132:total'], 'Internal referrals are not referrals out');
        $this->assertEquals(1, $r['values']['row_133:total']);
        $mp = array_column($r['evidence']['row_150:total'] ?? [], 'patient_id');
        $this->assertContains($tested->id, $mp);
        $this->assertNotContains($pending->id, $mp, 'A test with no result has not been done yet');
        $this->assertContains($tested->id, array_column($r['evidence']['row_151:total'] ?? [], 'patient_id'));
    }

    /** @test */
    public function test_amoxicillin_is_not_counted_as_a_bed_net_and_prescribing_is_not_dispensing()
    {
        $woman = $this->patient();
        DB::table('maternity_enrollments')->insert(['patient_id' => $woman->id, 'enrolled_by' => $this->doctor->id, 'enrollment_date' => $this->day, 'status' => 'active', 'lmp' => '2029-10-01', 'edd' => '2030-07-08', 'created_at' => $this->day, 'updated_at' => $this->day]);
        $amox = DB::table('products')->insertGetId(['user_id' => $this->doctor->id, 'product_name' => 'AMOXICILLIN 500MG CAPS test', 'category_id' => 1, 'status' => 1, 'created_at' => $this->day, 'updated_at' => $this->day]);
        $fersolate = DB::table('products')->insertGetId(['user_id' => $this->doctor->id, 'product_name' => 'FERROUS SULPHATE 200mg test', 'category_id' => 1, 'status' => 1, 'created_at' => $this->day, 'updated_at' => $this->day]);
        DB::table('product_requests')->insert(['patient_id' => $woman->id, 'product_id' => $amox, 'qty' => 1, 'status' => 3, 'dispense_date' => $this->day, 'created_at' => $this->day, 'updated_at' => $this->day]);
        DB::table('product_requests')->insert(['patient_id' => $woman->id, 'product_id' => $fersolate, 'qty' => 1, 'status' => 1, 'created_at' => $this->day, 'updated_at' => $this->day]);

        $r = $this->build();
        $this->assertEmpty($r['evidence']['row_30:total'] ?? [], 'Amoxi"cillin" is not an LLIN');
        $this->assertEmpty($r['evidence']['row_31:total'] ?? [], 'Prescribed but not dispensed is not given');
    }
}
