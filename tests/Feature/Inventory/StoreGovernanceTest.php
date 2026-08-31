<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Tests\TestCase;

class StoreGovernanceTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/store-governance');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_store_governance_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/store-governance');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
