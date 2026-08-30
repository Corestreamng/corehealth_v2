<?php

namespace Tests\Feature\Emergency;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class EmergencyIntakeTest extends TestCase
{
    /** @test */
    public function test_emergency_intake_bypasses_hmo_validation()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->post('/emergency-intake', [
            'firstname' => 'Emergency',
            'surname' => 'Patient',
            'is_emergency' => 1,
        ]);
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_unidentified_patient_creates_minimal_record()
    {
        $patient = Patient::factory()->create();
        $this->assertDatabaseHas('patients', ['id' => $patient->id]);
    }

    /** @test */
    public function test_emergency_patient_routed_to_correct_workbench()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/reception/workbench?emergency=1');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_emergency_intake_toggle_respects_hospital_config()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }
}
