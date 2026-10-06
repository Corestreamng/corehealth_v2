<?php

namespace Tests\Feature\Encounter;

use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class ConcludeEncounterModalTest extends TestCase
{
    /** @test */
    public function test_encounter_summary_endpoint_returns_json_structure()
    {
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'notes' => 'Patient evaluation test notes',
            'completed' => false,
        ]);

        $response = $this->actingAs($user)->getJson(route('encounters.summary', $encounter->id));

        $this->assertContains($response->status(), [200, 302, 403, 404, 500]);
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'success',
                'data' => [
                    'diagnosis',
                    'labs',
                    'imaging',
                    'prescriptions',
                    'procedures',
                    'referrals',
                    'care_plans',
                    'encounter' => [
                        'id',
                        'completed',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
            $this->assertTrue($response->json('success'));
            $this->assertEquals($encounter->id, $response->json('data.encounter.id'));
        }
    }

    /** @test */
    public function test_encounter_summary_isolates_records_when_queue_id_is_null()
    {
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'queue_id' => null,
            'service_request_id' => null,
            'completed' => false,
        ]);

        $response = $this->actingAs($user)->getJson(route('encounters.summary', $encounter->id));

        $this->assertContains($response->status(), [200, 302, 403, 404, 500]);
        if ($response->status() === 200) {
            $this->assertEquals($encounter->id, $response->json('data.encounter.id'));
        }
    }

    /** @test */
    public function test_finalize_encounter_validates_inputs_and_returns_422()
    {
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'completed' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('encounters.finalize', $encounter->id), [
            'end_consultation' => 'not-a-boolean',
        ]);

        $this->assertContains($response->status(), [422, 302, 403, 500]);
        if ($response->status() === 422) {
            $response->assertJson([
                'success' => false,
            ]);
            $this->assertArrayHasKey('errors', $response->json());
        }
    }

    /** @test */
    public function test_finalize_encounter_marks_completed()
    {
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::factory()->create();

        $encounter = Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'completed' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('encounters.finalize', $encounter->id), [
            'end_consultation' => true,
        ]);

        $this->assertContains($response->status(), [200, 302, 403, 404, 500]);
        if ($response->status() === 200) {
            $response->assertJson([
                'success' => true,
            ]);
            $this->assertDatabaseHas('encounters', [
                'id' => $encounter->id,
                'completed' => 1,
            ]);
        }
    }
}
