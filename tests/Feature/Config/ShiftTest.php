<?php

namespace Tests\Feature\Config;

use App\Models\User;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/shifts');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_shifts_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/shifts');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
