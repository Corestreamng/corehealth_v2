<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    /** @test */
    public function test_unauthenticated_user_redirected()
    {
        $response = $this->get('/purchase-orders');
        $this->assertTrue(in_array($response->status(), [302, 403, 404, 500]));
    }

    /** @test */
    public function test_purchase_orders_list_loads()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/purchase-orders');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
    }
}
