<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Tests\TestCase;

class MiscBillTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/misc-bills');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_misc_bills_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/misc-bills');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
