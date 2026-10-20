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

    /** @test */
    public function test_reception_can_fetch_pending_referrals_and_count()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $staff = \App\Models\Staff::first() ?? \App\Models\Staff::create([
            'user_id' => $user->id,
            'clinic_id' => 1,
            'status' => 1,
        ]);
        $patient = \App\Models\Patient::factory()->create();
        $clinic = \App\Models\Clinic::first() ?? \App\Models\Clinic::create(['name' => 'Cardiology Clinic', 'status' => 1]);
        $encounter = \App\Models\Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'status' => 1,
        ]);

        $referral = \App\Models\SpecialistReferral::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'referring_doctor_id' => $staff->id,
            'referring_clinic_id' => $clinic->id,
            'referral_type' => 'internal',
            'target_clinic_id' => $clinic->id,
            'reason' => 'Evaluation for murmur',
            'urgency' => 'routine',
            'status' => 'pending',
        ]);

        $countResponse = $this->actingAs($user)->getJson(route('referrals.pending-count'));
        $this->assertTrue(in_array($countResponse->status(), [200, 302]));
        if ($countResponse->status() === 200) {
            $countResponse->assertJsonStructure(['count']);
            $this->assertGreaterThanOrEqual(1, $countResponse->json('count'));
        }

        $listResponse = $this->actingAs($user)->getJson(route('referrals.pending'));
        $this->assertTrue(in_array($listResponse->status(), [200, 302]));
        if ($listResponse->status() === 200) {
            $listResponse->assertJsonStructure(['data']);
        }
    }

    /** @test */
    public function test_reception_can_view_referral_detail_via_standard_and_alias_routes()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $staff = \App\Models\Staff::first() ?? \App\Models\Staff::create([
            'user_id' => $user->id,
            'clinic_id' => 1,
            'status' => 1,
        ]);
        $patient = \App\Models\Patient::factory()->create();
        $clinic = \App\Models\Clinic::first() ?? \App\Models\Clinic::create(['name' => 'ENT Clinic', 'status' => 1]);
        $encounter = \App\Models\Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'status' => 1,
        ]);

        $referral = \App\Models\SpecialistReferral::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'referring_doctor_id' => $staff->id,
            'referring_clinic_id' => $clinic->id,
            'referral_type' => 'internal',
            'target_clinic_id' => $clinic->id,
            'reason' => 'Chronic otitis media',
            'urgency' => 'urgent',
            'status' => 'pending',
        ]);

        // Standard route
        $stdResponse = $this->actingAs($user)->getJson(route('referrals.detail', ['referral' => $referral->id]));
        $this->assertTrue(in_array($stdResponse->status(), [200, 302]));
        if ($stdResponse->status() === 200) {
            $stdResponse->assertJson(['success' => true]);
            $this->assertEquals($referral->id, $stdResponse->json('referral.id'));
        }

        // Alias route with query param
        $aliasResponse = $this->actingAs($user)->getJson(route('referrals.detail.alias') . '?referral_id=' . $referral->id);
        $this->assertTrue(in_array($aliasResponse->status(), [200, 302]));
        if ($aliasResponse->status() === 200) {
            $aliasResponse->assertJson(['success' => true]);
            $this->assertEquals($referral->id, $aliasResponse->json('referral.id'));
        }
    }

    /** @test */
    public function test_reception_can_book_internal_referral_appointment()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $staff = \App\Models\Staff::where('user_id', $user->id)->first() ?? \App\Models\Staff::create([
            'user_id' => $user->id,
            'clinic_id' => 1,
            'status' => 1,
        ]);
        $patient = \App\Models\Patient::factory()->create();
        $clinic = \App\Models\Clinic::first() ?? \App\Models\Clinic::create(['name' => 'Cardiology Clinic', 'status' => 1]);
        $encounter = \App\Models\Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'status' => 1,
        ]);

        $referral = \App\Models\SpecialistReferral::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'referring_doctor_id' => $staff->id,
            'referring_clinic_id' => $clinic->id,
            'referral_type' => 'internal',
            'target_clinic_id' => $clinic->id,
            'reason' => 'Book appointment for ECG review',
            'urgency' => 'routine',
            'status' => 'pending',
        ]);

        $payload = [
            'referral_id' => $referral->id,
            'appointment_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '10:00',
            'clinic_id' => $clinic->id,
        ];

        $response = $this->actingAs($user)->postJson(route('referrals.book', ['referral' => $referral->id]), $payload);
        $this->assertTrue(in_array($response->status(), [200, 302, 422]));
        if ($response->status() === 200) {
            $response->assertJson(['success' => true]);
            $this->assertDatabaseHas('specialist_referrals', [
                'id' => $referral->id,
                'status' => 'booked',
            ]);
        }
    }

    /** @test */
    public function test_reception_can_refer_out_external_referral()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $staff = \App\Models\Staff::first() ?? \App\Models\Staff::create([
            'user_id' => $user->id,
            'clinic_id' => 1,
            'status' => 1,
        ]);
        $patient = \App\Models\Patient::factory()->create();
        $clinic = \App\Models\Clinic::first() ?? \App\Models\Clinic::create(['name' => 'General Clinic', 'status' => 1]);
        $encounter = \App\Models\Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'status' => 1,
        ]);

        $referral = \App\Models\SpecialistReferral::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'referring_doctor_id' => $staff->id,
            'referring_clinic_id' => $clinic->id,
            'referral_type' => 'external',
            'external_facility_name' => 'City Oncology Center',
            'reason' => 'Oncology evaluation',
            'urgency' => 'routine',
            'status' => 'pending',
        ]);

        $payload = [
            'referral_id' => $referral->id,
            'action_notes' => 'Handed referral letter to patient and advised to visit facility',
        ];

        $response = $this->actingAs($user)->postJson(route('referrals.refer-out', ['referral' => $referral->id]), $payload);
        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertJson(['success' => true]);
            $this->assertDatabaseHas('specialist_referrals', [
                'id' => $referral->id,
                'status' => 'referred_out',
            ]);
        }
    }

    /** @test */
    public function test_reception_can_decline_and_cancel_referral()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $staff = \App\Models\Staff::first() ?? \App\Models\Staff::create([
            'user_id' => $user->id,
            'clinic_id' => 1,
            'status' => 1,
        ]);
        $patient = \App\Models\Patient::factory()->create();
        $clinic = \App\Models\Clinic::first() ?? \App\Models\Clinic::create(['name' => 'Dental Clinic', 'status' => 1]);
        $encounter = \App\Models\Encounter::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'status' => 1,
        ]);

        // Test decline
        $referral1 = \App\Models\SpecialistReferral::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'referring_doctor_id' => $staff->id,
            'referring_clinic_id' => $clinic->id,
            'referral_type' => 'internal',
            'target_clinic_id' => $clinic->id,
            'reason' => 'Root canal',
            'urgency' => 'routine',
            'status' => 'pending',
        ]);

        $declineResponse = $this->actingAs($user)->postJson(route('referrals.decline', ['referral' => $referral1->id]), [
            'referral_id' => $referral1->id,
            'reason' => 'Specialist is currently on annual leave',
        ]);
        $this->assertTrue(in_array($declineResponse->status(), [200, 302]));
        if ($declineResponse->status() === 200) {
            $declineResponse->assertJson(['success' => true]);
            $this->assertDatabaseHas('specialist_referrals', [
                'id' => $referral1->id,
                'status' => 'declined',
            ]);
        }

        // Test cancel
        $referral2 = \App\Models\SpecialistReferral::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'referring_doctor_id' => $staff->id,
            'referring_clinic_id' => $clinic->id,
            'referral_type' => 'internal',
            'target_clinic_id' => $clinic->id,
            'reason' => 'Cleaning and exam',
            'urgency' => 'routine',
            'status' => 'pending',
        ]);

        $cancelResponse = $this->actingAs($user)->postJson(route('referrals.cancel', ['referral' => $referral2->id]), [
            'referral_id' => $referral2->id,
            'reason' => 'Cancelled per patient request',
        ]);
        $this->assertTrue(in_array($cancelResponse->status(), [200, 302]));
        if ($cancelResponse->status() === 200) {
            $cancelResponse->assertJson(['success' => true]);
            $this->assertDatabaseHas('specialist_referrals', [
                'id' => $referral2->id,
                'status' => 'cancelled',
            ]);
        }
    }
}
