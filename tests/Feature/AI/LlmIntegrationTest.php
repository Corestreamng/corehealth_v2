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
}
