<?php

namespace Tests\Feature\AI;

use App\Models\Patient;
use App\Models\User;
use App\Services\OrderCatalogRegistryService;
use Tests\TestCase;

class ClinicalAiScribeStudioTest extends TestCase
{
    /** @test */
    public function test_scribe_process_endpoint_requires_authentication()
    {
        $response = $this->postJson(route('llm.clinical-scribe.process'), [
            'transcript' => 'Patient complains of fever',
        ]);

        $this->assertEquals(401, $response->status());
    }

    /** @test */
    public function test_scribe_process_endpoint_validates_empty_inputs()
    {
        $user = User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->postJson(route('llm.clinical-scribe.process'), [
            'transcript' => '',
            'editor_content' => '',
        ]);

        $this->assertTrue(in_array($response->status(), [403, 422]));
    }

    /** @test */
    public function test_scribe_convert_format_endpoint_requires_sections()
    {
        $user = User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->postJson(route('llm.clinical-scribe.convert-format'), [
            'from_taxonomy' => 'soap',
            'to_taxonomy' => 'clerking',
        ]);

        $this->assertTrue(in_array($response->status(), [403, 422]));
    }

    /** @test */
    public function test_scribe_sign_endpoint_creates_audit_record()
    {
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($user)->postJson(route('llm.clinical-scribe.sign'), [
            'patient_id' => $patient->id,
            'role' => 'doctor',
            'taxonomy_type' => 'clerking',
            'final_signed_note' => '<h3>Presenting Complaint</h3><p>Headache for 2 days.</p>',
            'original_transcript' => 'Patient complains of headache for two days.',
            'structured_note_json' => [
                ['key' => 'PC', 'title' => 'Presenting Complaint', 'content' => 'Headache for 2 days.'],
            ],
            'orders_dispatched_json' => [
                ['item_name' => 'Paracetamol 500mg', 'order_type' => 'PRODUCT'],
            ],
        ]);

        $this->assertEquals(200, $response->status());
        $response->assertJsonStructure(['success', 'message', 'audit_id']);
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('ai_scribe_audits', [
            'id' => $response->json('audit_id'),
            'patient_id' => $patient->id,
            'role' => 'doctor',
            'taxonomy_type' => 'clerking',
        ]);
    }

    /** @test */
    public function test_catalog_registry_resolves_categories_and_item_grounding()
    {
        $registry = app(OrderCatalogRegistryService::class);
        $this->assertNotNull($registry);

        $promptContext = $registry->getCategoryPromptContext();
        $this->assertStringContainsString('SERVICES', $promptContext);
        $this->assertStringContainsString('PRODUCTS', $promptContext);
    }
}
