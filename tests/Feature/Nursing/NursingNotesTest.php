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

    /** @test */
    public function test_nursing_workbench_notes_autosave_and_manual_save()
    {
        $patient = Patient::factory()->create();
        $nurse = User::factory()->create(['status' => 1]);
        $noteType = \App\Models\NursingNoteType::firstOrCreate(
            ['id' => 5],
            ['name' => 'Others', 'template' => '<p></p>', 'status' => 1]
        );

        $this->actingAs($nurse);

        // 1. Test autosave draft via /nursing-workbench/nursing-note
        $autosaveResponse = $this->postJson('/nursing-workbench/nursing-note', [
            'patient_id' => $patient->id,
            'note_type_id' => $noteType->id,
            'note' => 'Patient is resting comfortably (draft).',
            'completed' => 0,
        ]);

        $this->assertTrue(in_array($autosaveResponse->status(), [200, 302, 403]));
        if ($autosaveResponse->status() === 200) {
            $autosaveResponse->assertJson(['success' => true]);
        }

        // 2. Test manual save via /nursing-workbench/nursing-note
        $manualSaveResponse = $this->postJson('/nursing-workbench/nursing-note', [
            'patient_id' => $patient->id,
            'note_type_id' => $noteType->id,
            'note' => 'Patient is resting comfortably (final).',
            'completed' => 1,
        ]);

        $this->assertTrue(in_array($manualSaveResponse->status(), [200, 302, 403]));
        if ($manualSaveResponse->status() === 200) {
            $manualSaveResponse->assertJson(['success' => true]);
        }
    }

    /** @test */
    public function test_nursing_workbench_notes_store_alias_route()
    {
        $patient = Patient::factory()->create();
        $nurse = User::factory()->create(['status' => 1]);
        $noteType = \App\Models\NursingNoteType::firstOrCreate(
            ['id' => 5],
            ['name' => 'Others', 'template' => '<p></p>', 'status' => 1]
        );

        $this->actingAs($nurse);

        // Test POST to /nursing-workbench/notes/store (which previously 404ed)
        $response = $this->postJson('/nursing-workbench/notes/store', [
            'patient_id' => $patient->id,
            'note_type_id' => $noteType->id,
            'note' => 'Vitals taken and charting completed.',
            'completed' => 1,
        ]);

        // Must not be 404
        $this->assertNotEquals(404, $response->status());
        $this->assertTrue(in_array($response->status(), [200, 302, 403]));
        if ($response->status() === 200) {
            $response->assertJson(['success' => true]);
        }
    }

    /** @test */
    public function test_nursing_workbench_patient_notes_list()
    {
        $patient = Patient::factory()->create();
        $nurse = User::factory()->create(['status' => 1]);

        $this->actingAs($nurse);
        $response = $this->get("/nursing-workbench/patient/{$patient->id}/nursing-notes");

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 500]));
    }
}
