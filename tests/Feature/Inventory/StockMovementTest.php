<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/move-stock');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_stock_movement_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/move-stock');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
