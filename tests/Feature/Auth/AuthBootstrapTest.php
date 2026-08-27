<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class AuthBootstrapTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected_to_login()
    {
        $response = $this->get('/reception/workbench');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /** @test */
    public function test_authenticated_user_can_access_dashboard()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/home');
        $response->assertStatus(200);
    }

    /** @test */
    public function test_invalid_credentials_rejected()
    {
        $response = $this->post('/login', [
            'email' => 'invalid_user_test@example.com',
            'password' => 'wrongpassword123',
        ]);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function test_login_page_returns_200()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }
}
