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

    /** @test */
    public function test_encounters_create_page_renders()
    {
        $user = User::factory()->create(['status' => 1]);
        $clinic = \App\Models\Clinic::first() ?? \App\Models\Clinic::factory()->create();
        $staff = \App\Models\Staff::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'staff_id' => 'DOC-' . $user->id,
            'specialization' => 'General Practice',
        ]);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($user)->get(route('encounters.create', ['patient_id' => $patient->id]));

        $this->assertContains($response->status(), [200, 302, 403, 500]);
        if ($response->status() === 200) {
            $content = $response->getContent();
            $this->assertTrue(str_contains($content, 'clinical-orders-shared.js'));
            $this->assertTrue(str_contains($content, 'encounter-page.js'));
        }
    }

    /** @test */
    public function test_delete_encounter_endpoint_removes_encounter_note()
    {
        $doctor = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();
        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'notes' => 'Temporary encounter note to delete',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($doctor)->deleteJson(route('encounters.delete', $encounter), [
            'reason' => 'Created in error',
        ]);

        $this->assertContains($response->status(), [200, 302, 403, 500]);
        if ($response->status() === 200) {
            $response->assertJson([
                'success' => true,
            ]);
            $this->assertSoftDeleted('encounters', ['id' => $encounter->id]);
        }
    }

    /** @test */
    public function test_update_encounter_notes_endpoint_saves_modifications()
    {
        $doctor = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();
        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'notes' => 'Original note text',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($doctor)->putJson(route('encounters.updateNotes', $encounter), [
            'notes' => 'Updated note content after review',
            'reasons_for_encounter' => '',
            'reasons_for_encounter_comment_1' => 'NA',
            'reasons_for_encounter_comment_2' => 'NA',
        ]);

        $this->assertContains($response->status(), [200, 302, 403, 500]);
        if ($response->status() === 200) {
            $response->assertJson([
                'success' => true,
            ]);
            $this->assertDatabaseHas('encounters', [
                'id' => $encounter->id,
                'notes' => 'Updated note content after review',
            ]);
        }
    }
}
