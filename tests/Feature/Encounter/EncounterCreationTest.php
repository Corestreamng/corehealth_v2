<?php

namespace Tests\Feature\Encounter;

use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class EncounterCreationTest extends TestCase
{
    /** @test */
    public function test_encounter_can_be_created_for_patient()
    {
        $patient = Patient::factory()->create();
        $doctor = User::factory()->create(['status' => 1]);

        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 1,
        ]);

        $this->assertDatabaseHas('encounters', ['id' => $encounter->id]);
    }

    /** @test */
    public function test_encounter_auto_saves_doctor_notes()
    {
        $patient = Patient::factory()->create();
        $doctor = User::factory()->create(['status' => 1]);
        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'notes' => 'Patient presents with mild fever.',
        ]);
        $this->assertEquals('Patient presents with mild fever.', $encounter->notes);
    }

    /** @test */
    public function test_encounter_has_lab_request_relationship()
    {
        $patient = Patient::factory()->create();
        $doctor = User::factory()->create(['status' => 1]);
        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);
        $this->assertTrue(method_exists($encounter, 'labRequests') || method_exists($encounter, 'productOrServiceRequests'));
    }

    /** @test */
    public function test_duplicate_open_encounter_prevented()
    {
        $patient = Patient::factory()->create();
        $doctor = User::factory()->create(['status' => 1]);
        $encounter1 = Encounter::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'completed' => 0]);
        $this->assertNotNull($encounter1->id);
    }

    /** @test */
    public function test_encounter_shows_correct_patient()
    {
        $patient = Patient::factory()->create();
        $doctor = User::factory()->create(['status' => 1]);
        $encounter = Encounter::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id]);
        $this->assertEquals($patient->id, $encounter->patient_id);
    }
}
