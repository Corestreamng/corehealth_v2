<?php

namespace Tests\Feature\Nhmis;

use App\Models\ApplicationStatu;
use App\Models\LabServiceRequest;
use App\Models\NhmisServiceMapping;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Tests\TestCase;

class NhmisResultsTrackingToggleTest extends TestCase
{
    protected User $adminUser;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::find(1) ?? User::factory()->create(['status' => 1]);
        $this->patient = Patient::first() ?? Patient::factory()->create();
    }

    protected function tearDown(): void
    {
        clearAppSettingsCache();
        parent::tearDown();
    }

    private function setTracking(bool $enabled): void
    {
        $config = ApplicationStatu::first();
        $config->enable_nhmis_results_tracking = $enabled;
        $config->save();
        clearAppSettingsCache();
    }

    private function mappedLabRequest(): LabServiceRequest
    {
        $service = Service::create([
            'user_id' => $this->adminUser->id,
            'service_name' => 'Malaria Microscopy Toggle Test ' . uniqid(),
            'category_id' => 2,
            'status' => 1,
        ]);
        NhmisServiceMapping::create([
            'indicator_code' => 'malaria_microscopy',
            'service_id' => $service->id,
            'service_type' => 'investigation',
            'supported_outcomes' => ['Negative', 'Positive (+)'],
            'positive_outcomes' => ['Positive (+)'],
        ]);

        return LabServiceRequest::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->adminUser->id,
            'service_id' => $service->id,
            'status' => 2,
        ]);
    }

    /** @test */
    public function test_disabled_tracking_lets_mapped_lab_result_save_without_outcome()
    {
        $this->setTracking(false);
        $labReq = $this->mappedLabRequest();

        $response = $this->actingAs($this->adminUser)->postJson('/lab-workbench/save-result', [
            'invest_res_entry_id' => $labReq->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>Malaria parasites seen: Positive (+)</p>',
            'nhmis_outcome' => 'positive',
            'entry_source' => 'lab_workbench',
        ]);

        $this->assertNotEquals(422, $response->status(), 'Disabled tracking must not block result entry');
        $labReq->refresh();
        $this->assertNull($labReq->nhmis_classified_at, 'Disabled tracking must not classify the result');
    }

    /** @test */
    public function test_enabled_tracking_still_requires_outcome_for_mapped_lab_result()
    {
        $this->setTracking(true);
        $labReq = $this->mappedLabRequest();

        $response = $this->actingAs($this->adminUser)->postJson('/lab-workbench/save-result', [
            'invest_res_entry_id' => $labReq->id,
            'invest_res_template_version' => 1,
            'invest_res_template_submited' => '<p>Normal</p>',
            'entry_source' => 'lab_workbench',
        ]);

        $this->assertEquals(422, $response->status());
    }

    /** @test */
    public function test_saving_empty_service_list_unlinks_indicator_and_keeps_outcome_rules()
    {
        $service = Service::create([
            'user_id' => $this->adminUser->id,
            'service_name' => 'HBsAg Link Test ' . uniqid(),
            'category_id' => 2,
            'status' => 1,
        ]);

        // Link via JSON payload with explicit outcome rules
        $link = $this->actingAs($this->adminUser)->postJson('/nhmis-workbench/service-mappings', [
            'mappings' => [
                'hepatitis_b' => [
                    'service_ids' => [$service->id],
                    'supported_outcomes' => ['Reactive', 'Non-Reactive'],
                    'positive_outcomes' => ['Reactive'],
                ],
            ],
        ]);
        $this->assertEquals(200, $link->status());
        $saved = NhmisServiceMapping::where('indicator_code', 'hepatitis_b')->get();
        $this->assertEquals([$service->id], $saved->pluck('service_id')->all());
        $this->assertEquals(['Reactive'], $saved->first()->positive_outcomes);

        // Unlink everything: an empty array must reach the server and clear the links
        $unlink = $this->actingAs($this->adminUser)->postJson('/nhmis-workbench/service-mappings', [
            'mappings' => ['hepatitis_b' => ['service_ids' => []]],
        ]);
        $this->assertEquals(200, $unlink->status());
        $this->assertEquals(0, NhmisServiceMapping::where('indicator_code', 'hepatitis_b')->count());
    }

    /** @test */
    public function test_keyword_matching_is_whole_word_only()
    {
        $this->assertTrue(NhmisServiceMapping::nameMatchesKeyword('Haemoglobin (Hb)', 'hb'));
        $this->assertFalse(NhmisServiceMapping::nameMatchesKeyword('HBsAg Viral Load', 'hb'));
        $this->assertFalse(NhmisServiceMapping::nameMatchesKeyword('ALT (SGPT)', 'pt'));
        $this->assertFalse(NhmisServiceMapping::nameMatchesKeyword('Nasal Packing (Nurse)', 'pac'));
        $this->assertEquals('caesarean_section', NhmisServiceMapping::matchIndicatorByName('Emergency Caesarean Section', 'procedure'));
    }

    /** @test */
    public function test_unlinked_catalogue_service_is_not_guessed_from_its_name()
    {
        $service = Service::create([
            'user_id' => $this->adminUser->id,
            'service_name' => 'Hepatitis B Unlinked Guess Test ' . uniqid(),
            'category_id' => 2,
            'status' => 1,
        ]);

        $linkedOnly = $this->actingAs($this->adminUser)->getJson('/nhmis-workbench/service-mapping/' . $service->id . '?service_name=' . urlencode($service->service_name));
        $this->assertEquals(200, $linkedOnly->status());
        $this->assertFalse($linkedOnly->json('is_mapped'), 'A catalogue service with no link must not show the NHMIS bar');

        $freeText = $this->actingAs($this->adminUser)->getJson('/nhmis-workbench/service-mapping/0?service_name=' . urlencode($service->service_name));
        $this->assertTrue($freeText->json('is_mapped'), 'Free-text items still resolve by whole-word name');
        $this->assertEquals('hepatitis_b', $freeText->json('indicator_code'));
    }
}
