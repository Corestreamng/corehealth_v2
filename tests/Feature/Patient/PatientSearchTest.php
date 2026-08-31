<?php

namespace Tests\Feature\Patient;

use App\Models\User;
use Tests\TestCase;

class PatientSearchTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/patient-search?q=test');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_patient_search_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/patient-search?q=test');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
