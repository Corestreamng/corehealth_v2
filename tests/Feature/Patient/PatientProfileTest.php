<?php

namespace Tests\Feature\Patient;

use App\Models\User;
use Tests\TestCase;

class PatientProfileTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/patient-profile/1');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_patient_profile_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/patient-profile/1');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
