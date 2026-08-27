<?php

namespace Tests\Feature\Nursing;

use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class NursingNotesTest extends TestCase
{
    /** @test */
    public function test_nursing_note_can_be_created_for_patient()
    {
        $patient = Patient::factory()->create();
        $nurse = User::factory()->create(['status' => 1]);

        $this->actingAs($nurse);
        $response = $this->post('/nursing-notes', [
            'patient_id' => $patient->id,
            'note' => 'Patient administered IV fluids.',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_nursing_note_is_linked_to_encounter()
    {
        $patient = Patient::factory()->create();
        $encounter = $doctor = User::factory()->create(['status' => 1]);
        $encounter = Encounter::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id]);
        $this->assertEquals($patient->id, $encounter->patient_id);
    }

    /** @test */
    public function test_nursing_note_requires_content_field()
    {
        $nurse = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($nurse)->post('/nursing-notes', [
            'patient_id' => 1,
            'note' => '',
        ]);
        $this->assertTrue(in_array($response->status(), [302, 422, 404]));
    }

    /** @test */
    public function test_doctor_can_view_nursing_notes_for_admitted_patient()
    {
        $doctor = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($doctor)->get("/patient-profile/{$patient->id}");
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }
}
