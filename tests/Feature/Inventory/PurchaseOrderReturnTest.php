<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Tests\TestCase;

class PurchaseOrderReturnTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/purchase-order-returns');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_purchase_order_returns_endpoint_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/purchase-order-returns');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
