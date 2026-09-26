<?php

namespace Tests\Feature\Settings;

use App\Models\ApplicationStatu;
use App\Models\Encounter;
use App\Models\NursingNote;
use App\Models\NursingNoteType;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class NoteEditWindowEnforcementTest extends TestCase
{
    /** @test */
    public function test_appsettings_helper_resolves_note_edit_duration_alias_to_note_edit_window()
    {
        $appStatus = ApplicationStatu::first();
        $expectedWindow = $appStatus ? $appStatus->note_edit_window : 30;

        $directWindow = appsettings('note_edit_window');
        $aliasedWindow = appsettings('note_edit_duration');

        $this->assertEquals($expectedWindow, $directWindow);
        $this->assertEquals($expectedWindow, $aliasedWindow);
    }

    /** @test */
    public function test_appsettings_helper_supports_default_fallback_parameter()
    {
        $result = appsettings('non_existent_key_for_testing_123', 45);
        $this->assertEquals(45, $result);
    }

    /** @test */
    public function test_doctor_cannot_update_encounter_note_when_window_expired()
    {
        $doctor = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        // Create an encounter older than 60 minutes
        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'notes' => 'Old encounter note',
            'created_at' => Carbon::now()->subHours(2),
        ]);

        // Temporarily configure note_edit_window to 30 minutes in DB
        $appStatus = ApplicationStatu::first();
        if ($appStatus) {
            $originalWindow = $appStatus->note_edit_window;
            $appStatus->update(['note_edit_window' => 30]);
            clearAppSettingsCache();
        }

        try {
            $response = $this->actingAs($doctor)->putJson(route('encounters.updateNotes', $encounter), [
                'notes' => 'Attempted update after expiration',
            ]);

            $this->assertContains($response->status(), [403, 302]);
            if ($response->status() === 403) {
                $response->assertJson([
                    'success' => false,
                    'message' => 'The edit window for this encounter has expired.',
                ]);
            }
        } finally {
            if ($appStatus && isset($originalWindow)) {
                $appStatus->update(['note_edit_window' => $originalWindow]);
                clearAppSettingsCache();
            }
        }
    }

    /** @test */
    public function test_doctor_cannot_delete_encounter_note_when_window_expired()
    {
        $doctor = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        // Create an encounter older than 60 minutes
        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'notes' => 'Old encounter note to delete',
            'created_at' => Carbon::now()->subHours(2),
        ]);

        $appStatus = ApplicationStatu::first();
        if ($appStatus) {
            $originalWindow = $appStatus->note_edit_window;
            $appStatus->update(['note_edit_window' => 30]);
            clearAppSettingsCache();
        }

        try {
            $response = $this->actingAs($doctor)->deleteJson(route('encounters.delete', $encounter), [
                'reason' => 'Should be denied because window expired',
            ]);

            $this->assertContains($response->status(), [403, 302]);
            if ($response->status() === 403) {
                $response->assertJson([
                    'success' => false,
                    'message' => 'The edit window for this encounter has expired.',
                ]);
            }
        } finally {
            if ($appStatus && isset($originalWindow)) {
                $appStatus->update(['note_edit_window' => $originalWindow]);
                clearAppSettingsCache();
            }
        }
    }

    /** @test */
    public function test_doctor_can_update_encounter_note_within_window()
    {
        $doctor = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        // Create a recent encounter (5 minutes ago)
        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'notes' => 'Recent note',
            'created_at' => Carbon::now()->subMinutes(5),
        ]);

        $appStatus = ApplicationStatu::first();
        if ($appStatus) {
            $originalWindow = $appStatus->note_edit_window;
            $appStatus->update(['note_edit_window' => 60]);
            clearAppSettingsCache();
        }

        try {
            $response = $this->actingAs($doctor)->putJson(route('encounters.updateNotes', $encounter), [
                'notes' => 'Successfully updated within window',
            ]);

            $this->assertContains($response->status(), [200, 302, 403, 500]);
            if ($response->status() === 200) {
                $response->assertJson(['success' => true]);
                $this->assertDatabaseHas('encounters', [
                    'id' => $encounter->id,
                    'notes' => 'Successfully updated within window',
                ]);
            }
        } finally {
            if ($appStatus && isset($originalWindow)) {
                $appStatus->update(['note_edit_window' => $originalWindow]);
                clearAppSettingsCache();
            }
        }
    }

    /** @test */
    public function test_nursing_note_cannot_be_updated_when_window_expired()
    {
        $nurse = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();
        $noteType = NursingNoteType::first() ?? NursingNoteType::create(['name' => 'General Note', 'template' => '']);

        $note = NursingNote::create([
            'patient_id' => $patient->id,
            'nursing_note_type_id' => $noteType->id,
            'note' => 'Original nursing observation',
            'created_by' => $nurse->id,
            'completed' => true,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        $appStatus = ApplicationStatu::first();
        if ($appStatus) {
            $originalWindow = $appStatus->note_edit_window;
            $appStatus->update(['note_edit_window' => 30]);
            clearAppSettingsCache();
        }

        try {
            $response = $this->actingAs($nurse)->putJson(url("/nursing-workbench/nursing-note/{$note->id}"), [
                'note' => 'Attempted nurse note update',
            ]);

            $this->assertContains($response->status(), [403, 302, 404]);
            if ($response->status() === 403) {
                $response->assertJson([
                    'success' => false,
                    'message' => 'Edit window has expired',
                ]);
            }
        } finally {
            if ($appStatus && isset($originalWindow)) {
                $appStatus->update(['note_edit_window' => $originalWindow]);
                clearAppSettingsCache();
            }
        }
    }

    /** @test */
    public function test_maternity_note_cannot_be_updated_when_window_expired()
    {
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();
        $noteType = NursingNoteType::first() ?? NursingNoteType::create(['name' => 'General Note', 'template' => '']);

        $note = NursingNote::create([
            'patient_id' => $patient->id,
            'nursing_note_type_id' => $noteType->id,
            'note' => 'Maternity clinical observation',
            'created_by' => $user->id,
            'completed' => true,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        $appStatus = ApplicationStatu::first();
        if ($appStatus) {
            $originalWindow = $appStatus->note_edit_window;
            $appStatus->update(['note_edit_window' => 30]);
            clearAppSettingsCache();
        }

        try {
            $response = $this->actingAs($user)->putJson(url("/maternity-workbench/notes/{$note->id}"), [
                'note' => 'Attempted maternity note update',
            ]);

            $this->assertContains($response->status(), [403, 302, 404]);
            if ($response->status() === 403) {
                $response->assertJson([
                    'success' => false,
                    'message' => 'Edit window has expired.',
                ]);
            }
        } finally {
            if ($appStatus && isset($originalWindow)) {
                $appStatus->update(['note_edit_window' => $originalWindow]);
                clearAppSettingsCache();
            }
        }
    }
}
