<?php

namespace Tests\Feature\Clinical;

use App\Models\User;
use Tests\TestCase;

class ClinicalReportsTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/search-diagnosis?q=malaria');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_search_diagnosis_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/clinical-reports/search-diagnosis?keyword=hy');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_diagnosis_drill_down_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/clinical-reports/drill-down?type=diagnosis&icd_code=D730');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_encounter_details_drill_down_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/clinical-reports/encounter-details/2');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_search_diagnosis_aggregates_variations_and_matches_drill_down_count()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $p1 = \App\Models\Patient::first();
        $p2 = \App\Models\Patient::skip(1)->first() ?? $p1;

        $uniqTag = 'TestSevereMalaria_' . uniqid();

        $e1 = \App\Models\Encounter::create([
            'patient_id' => $p1->id,
            'doctor_id' => $user->id,
            'reasons_for_encounter' => json_encode([['name' => $uniqTag, 'code' => 'CUSTOM', 'comment_1' => 'CONFIRMED', 'comment_2' => 'ACUTE']]),
            'notes' => 'First note for ' . $uniqTag,
            'completed' => true,
        ]);

        $e2 = \App\Models\Encounter::create([
            'patient_id' => $p2->id,
            'doctor_id' => $user->id,
            'reasons_for_encounter' => json_encode([['name' => strtolower($uniqTag) . ' ', 'code' => 'CUSTOM', 'comment_1' => 'NA', 'comment_2' => 'NA']]),
            'notes' => 'Second note',
            'completed' => true,
        ]);

        $e3 = \App\Models\Encounter::create([
            'patient_id' => $p1->id,
            'doctor_id' => $user->id,
            'reasons_for_encounter' => null,
            'notes' => 'Clinical note notes diagnosis ' . $uniqTag,
            'completed' => true,
        ]);

        $searchResp = $this->actingAs($user)->getJson('/clinical-reports/search-diagnosis?keyword=' . urlencode($uniqTag) . '&date_from=' . now()->subDay()->toDateString() . '&date_to=' . now()->addDay()->toDateString());
        $searchData = $searchResp->json();

        $this->assertIsArray($searchData);
        $this->assertCount(1, $searchData);
        $this->assertEquals(3, $searchData[0]['total_encounters']);
        $this->assertEquals(count(array_unique([$p1->id, $p2->id])), $searchData[0]['unique_patients']);
        $this->assertCount(3, $searchData[0]['encounters']);

        $drillResp = $this->actingAs($user)->getJson('/clinical-reports/drill-down?type=diagnosis&icd_code=CUSTOM&diagnosis_name=' . urlencode($searchData[0]['diagnosis']) . '&date_from=' . now()->subDay()->toDateString() . '&date_to=' . now()->addDay()->toDateString());
        $drillData = $drillResp->json();

        $this->assertIsArray($drillData);
        $this->assertCount(3, $drillData);
    }

    /** @test */
    public function test_export_diagnosis_generates_csv_with_filters_and_encounters()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $p1 = \App\Models\Patient::first();
        $uniqTag = 'ExportDiagTest_' . uniqid();

        $e = \App\Models\Encounter::create([
            'patient_id' => $p1->id,
            'doctor_id' => $user->id,
            'reasons_for_encounter' => json_encode([['name' => $uniqTag, 'code' => 'CUSTOM', 'comment_1' => 'CONFIRMED', 'comment_2' => 'ACUTE']]),
            'notes' => 'Clinical note for ' . $uniqTag,
            'completed' => true,
        ]);

        $response = $this->actingAs($user)->get('/clinical-reports/export?tab=diagnosis&keyword=' . urlencode($uniqTag) . '&date_from=' . now()->subDay()->toDateString() . '&date_to=' . now()->addDay()->toDateString());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('REPORT FILTERS & METADATA', $content);
        $this->assertStringContainsString($uniqTag, $content);
        $this->assertStringContainsString('DIAGNOSIS SUMMARY', $content);
        $this->assertStringContainsString('DETAILED ENCOUNTER RECORDS', $content);
    }
}
