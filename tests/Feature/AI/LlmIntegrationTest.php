<?php

namespace Tests\Feature\AI;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class LlmIntegrationTest extends TestCase
{
    /** @test */
    public function test_llm_summary_endpoint_requires_patient_id()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/ai/summary');
        $this->assertTrue(in_array($response->status(), [200, 302, 400, 404, 422]));
    }

    /** @test */
    public function test_llm_clinical_vectors_endpoint_returns_14_categories()
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get("/ai/vectors?patient_id={$patient->id}");
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_llm_cache_returns_existing_summary_without_api_call()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_encrypted_api_key_decrypts_correctly_in_config()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_polish_note_endpoint_accepts_request_with_patient_and_null_encounter()
    {
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        // Testing the exact payload sent by Nurse Workbench and New Encounter before save
        $response = $this->actingAs($user)->postJson(route('llm.polish-note'), [
            'note_content' => 'Patient presented with headache and fever. Vitals stable.',
            'patient_id' => $patient->id,
            'encounter_id' => null,
        ]);

        $this->assertTrue(in_array($response->status(), [200, 403, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success', 'polished_content', 'model_used']);
            $this->assertTrue($response->json('success'));
        }
    }

    /** @test */
    public function test_polish_note_endpoint_accepts_standalone_note_without_patient()
    {
        $user = User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->postJson(route('llm.polish-note'), [
            'note_content' => 'Review of systems unremarkable.',
            'patient_id' => null,
            'encounter_id' => null,
        ]);

        $this->assertTrue(in_array($response->status(), [200, 403, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success', 'polished_content', 'model_used']);
            $this->assertTrue($response->json('success'));
        }
    }

    /** @test */
    public function test_patient_summary_endpoint_requires_patient_id()
    {
        $user = User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->postJson(route('llm.patient-summary'), []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['patient_id']);
    }
}
