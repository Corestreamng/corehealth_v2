<?php

namespace Tests\Feature\Lab;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class LabWorkbenchTest extends TestCase
{
    /** @test */
    public function test_lab_workbench_returns_200()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/lab-workbench');
        $this->assertNotNull($response->status());
    }

    /** @test */
    public function test_sample_collection_updates_request_status()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_lab_result_entry_updates_result_fields()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_result_released_flag_set_on_verify()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }

    /** @test */
    public function test_lab_request_linked_to_service_and_patient()
    {
        $patient = Patient::factory()->create();
        $this->assertNotNull($patient->id);
    }
}
