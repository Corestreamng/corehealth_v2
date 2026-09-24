<?php

namespace Tests\Feature\Clinical;

use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClinicalReportsBenchmarkTest extends TestCase
{
    /** @test */
    public function test_search_diagnosis_query_count_is_bounded_and_prevents_n_plus_one()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patients = Patient::take(3)->get();
        if ($patients->isEmpty()) {
            $this->markTestSkipped('No patients available for benchmark test.');
        }

        $uniqKeyword = 'BenchmarkDiag_' . uniqid();

        // Create multiple encounters across different patients and dates
        for ($i = 0; $i < 6; $i++) {
            $patient = $patients[$i % count($patients)];
            Encounter::create([
                'patient_id' => $patient->id,
                'doctor_id' => $user->id,
                'reasons_for_encounter' => json_encode([
                    ['name' => $uniqKeyword . ' Type ' . ($i % 2), 'code' => 'CUSTOM', 'comment_1' => 'CONFIRMED', 'comment_2' => 'ACUTE'],
                ]),
                'notes' => 'Consultation note for ' . $uniqKeyword,
                'completed' => true,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $startTime = microtime(true);
        $response = $this->actingAs($user)->getJson(
            '/clinical-reports/search-diagnosis?keyword=' . urlencode($uniqKeyword) .
            '&date_from=' . now()->subDay()->toDateString() .
            '&date_to=' . now()->addDay()->toDateString()
        );
        $duration = microtime(true) - $startTime;

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        // Assert query count is strictly bounded (preventing N+1 loops)
        // Expected: 1 main encounter query + eager loads (patient, user, hmo, doctor, queue, clinic) <= 7 queries total
        $this->assertLessThanOrEqual(
            7,
            count($queries),
            'searchDiagnosis executed ' . count($queries) . ' queries. Expected <= 7 bounded queries with no N+1 query loop.'
        );

        // Execution time assertion: should be fast (< 1.5s in test environment)
        $this->assertLessThan(1.5, $duration, 'searchDiagnosis took ' . round($duration, 3) . 's, exceeding performance threshold.');
    }

    /** @test */
    public function test_export_diagnosis_query_count_is_bounded_and_fast()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient available for export benchmark test.');
        }

        $uniqKeyword = 'ExportBench_' . uniqid();

        for ($i = 0; $i < 4; $i++) {
            Encounter::create([
                'patient_id' => $patient->id,
                'doctor_id' => $user->id,
                'reasons_for_encounter' => json_encode([
                    ['name' => $uniqKeyword, 'code' => 'CUSTOM', 'comment_1' => 'CONFIRMED', 'comment_2' => 'ACUTE'],
                ]),
                'notes' => 'Clinical notes for ' . $uniqKeyword,
                'completed' => true,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $startTime = microtime(true);
        $response = $this->actingAs($user)->get(
            '/clinical-reports/export?tab=diagnosis&keyword=' . urlencode($uniqKeyword) .
            '&date_from=' . now()->subDay()->toDateString() .
            '&date_to=' . now()->addDay()->toDateString()
        );
        $duration = microtime(true) - $startTime;

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        // Query count assertion: bounded with 0 N+1 lookups
        $this->assertLessThanOrEqual(
            7,
            count($queries),
            'exportDiagnosis executed ' . count($queries) . ' queries. Expected <= 7 bounded queries.'
        );

        $this->assertLessThan(1.5, $duration, 'exportDiagnosis took ' . round($duration, 3) . 's.');
    }

    /** @test */
    public function test_get_drill_down_details_diagnosis_query_count_is_bounded()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patient = Patient::first();
        if (!$patient) {
            $this->markTestSkipped('No patient available.');
        }

        $uniqKeyword = 'DrillBench_' . uniqid();

        Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'reasons_for_encounter' => json_encode([
                ['name' => $uniqKeyword, 'code' => 'CUSTOM', 'comment_1' => 'CONFIRMED', 'comment_2' => 'CHRONIC'],
            ]),
            'notes' => 'Drilldown test note',
            'completed' => true,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user)->getJson(
            '/clinical-reports/drill-down?type=diagnosis&icd_code=CUSTOM&diagnosis_name=' . urlencode($uniqKeyword) .
            '&date_from=' . now()->subDay()->toDateString() .
            '&date_to=' . now()->addDay()->toDateString()
        );

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);

        // Expected bounded queries: main query + eager relations <= 6
        $this->assertLessThanOrEqual(
            6,
            count($queries),
            'Drill down diagnosis executed ' . count($queries) . ' queries. Expected <= 6.'
        );
    }
}
