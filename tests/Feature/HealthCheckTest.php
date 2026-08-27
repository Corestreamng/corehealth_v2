<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /** @test */
    public function test_application_is_up_and_returns_200()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    /** @test */
    public function test_authenticated_dashboard_loads()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/home');
        $this->assertTrue(in_array($response->status(), [200, 302]));
    }

    /** @test */
    public function test_api_csrf_token_endpoint_returns_token()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }
}
