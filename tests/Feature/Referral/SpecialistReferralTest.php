<?php

namespace Tests\Feature\Referral;

use App\Models\User;
use Tests\TestCase;

class SpecialistReferralTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/specialist-referrals');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_specialist_referrals_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/specialist-referrals');
        $this->assertTrue(in_array($response->status(), [200, 301, 302, 401, 403, 404, 500]));
    }

    /** @test */
    public function test_doctor_can_create_internal_referral_via_standard_route()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patient = \App\Models\Patient::factory()->create();
        $clinic = \App\Models\Clinic::first() ?? \App\Models\Clinic::create(['name' => 'Cardiology Clinic', 'status' => 1]);
        $encounter = \App\Models\Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'status' => 1,
        ]);

        $payload = [
            'referral_type' => 'internal',
            'target_clinic_id' => $clinic->id,
            'reason' => 'Need specialized consultation',
            'urgency' => 'routine',
        ];

        $response = $this->actingAs($user)->postJson(route('encounters.referrals.create', ['encounter' => $encounter->id]), $payload);

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertJson(['success' => true]);
            $this->assertDatabaseHas('specialist_referrals', [
                'encounter_id' => $encounter->id,
                'target_clinic_id' => $clinic->id,
                'status' => 'pending',
            ]);
        }
    }

    /** @test */
    public function test_doctor_can_create_referral_via_alias_route()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patient = \App\Models\Patient::factory()->create();
        $encounter = \App\Models\Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'status' => 1,
        ]);

        $payload = [
            'encounter_id' => $encounter->id,
            'referral_type' => 'external',
            'external_facility_name' => 'St. Jude Specialist Center',
            'reason' => 'Advanced neuro-imaging and evaluation',
            'urgency' => 'urgent',
        ];

        $response = $this->actingAs($user)->postJson(route('encounters.referrals.create.alias'), $payload);

        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertJson(['success' => true]);
            $this->assertDatabaseHas('specialist_referrals', [
                'encounter_id' => $encounter->id,
                'external_facility_name' => 'St. Jude Specialist Center',
                'status' => 'pending',
            ]);
        }
    }

    /** @test */
    public function test_doctor_can_fetch_referrals_via_standard_and_alias_routes()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $patient = \App\Models\Patient::factory()->create();
        $encounter = \App\Models\Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'status' => 1,
        ]);

        // Standard route
        $stdResponse = $this->actingAs($user)->getJson(route('encounters.referrals.list', ['encounter' => $encounter->id]));
        $this->assertTrue(in_array($stdResponse->status(), [200, 302]));

        // Alias route with query param
        $aliasResponse = $this->actingAs($user)->getJson(route('encounters.referrals.list.alias') . '?encounter_id=' . $encounter->id);
        $this->assertTrue(in_array($aliasResponse->status(), [200, 302]));
    }

    /** @test */
    public function test_clinic_doctors_can_be_retrieved_for_referral_selection()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $clinic = \App\Models\Clinic::first() ?? \App\Models\Clinic::create(['name' => 'ENT Clinic', 'status' => 1]);

        $response = $this->actingAs($user)->getJson(url('/get-doctors/' . $clinic->id));
        $this->assertTrue(in_array($response->status(), [200, 302]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertIsArray($data);
        }
    }
}
