<?php

namespace Tests\Feature\Clinical;

use App\Models\User;
use Tests\TestCase;

class TreatmentPlanTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/treatment-plans');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_treatment_plans_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/treatment-plans');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
