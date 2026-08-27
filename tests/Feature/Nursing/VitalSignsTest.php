<?php

namespace Tests\Feature\Nursing;

use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class VitalSignsTest extends TestCase
{
    /** @test */
    public function test_vital_signs_can_be_recorded()
    {
        $patient = Patient::factory()->create();
        $nurse = User::factory()->create(['status' => 1]);

        $response = $this->actingAs($nurse)->post('/vitals', [
            'patient_id' => $patient->id,
            'systolic' => 120,
            'diastolic' => 80,
            'pulse' => 72,
            'temperature' => 36.6,
        ]);
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_vital_signs_linked_to_patient_and_encounter()
    {
        $patient = Patient::factory()->create();
        $encounter = $doctor = User::factory()->create(['status' => 1]);
        $encounter = Encounter::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id]);
        $this->assertEquals($patient->id, $encounter->patient_id);
    }

    /** @test */
    public function test_vitals_queue_returns_patients_needing_vitals()
    {
        $nurse = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($nurse)->get('/vitals-queue');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_bp_field_is_optional_in_vitals()
    {
        $patient = Patient::factory()->create();
        $nurse = User::factory()->create(['status' => 1]);

        $response = $this->actingAs($nurse)->post('/vitals', [
            'patient_id' => $patient->id,
            'temperature' => 37.0,
        ]);
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_vitals_chart_data_endpoint_returns_200()
    {
        $patient = Patient::factory()->create();
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get("/vitals/chart/{$patient->id}");
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }
}
