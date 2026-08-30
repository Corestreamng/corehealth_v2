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
        // Use an existing user from the test DB to avoid triggering factory observers
        $user = User::first();
        if (!$user) {
            $this->markTestSkipped('No users in test database.');

            return;
        }
        $response = $this->actingAs($user)->get('/home');
        $this->assertTrue(in_array($response->status(), [200, 302]));
    }

    /** @test */
    public function test_api_csrf_token_endpoint_returns_token()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $this->assertStringContainsString('_token', $response->content());
    }

    /** @test */
    public function test_unauthenticated_access_to_dashboard_redirects_to_login()
    {
        $response = $this->get('/home');
        $this->assertTrue(in_array($response->status(), [302, 401]));
    }
}
