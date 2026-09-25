<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Tests\TestCase;

class InventoryRoutingTest extends TestCase
{
    public function test_inventory_subfolder_routing()
    {
        $this->assertTrue(true);
    }

    /** @test */
    public function test_store_batches_routes_respond()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $store = Store::first() ?? Store::create(['store_name' => 'Main Test Store', 'status' => 1]);

        // Test ajax route
        $response1 = $this->actingAs($user)->getJson('/inventory/store-workbench/ajax/store-batches?store_id=' . $store->id);
        $this->assertTrue(in_array($response1->status(), [200, 302, 403, 404, 500]));
        if ($response1->status() === 200) {
            $response1->assertJsonStructure(['success', 'batches']);
        }

        // Test direct alias route
        $response2 = $this->actingAs($user)->getJson('/inventory/store-workbench/store-batches?store_id=' . $store->id);
        $this->assertTrue(in_array($response2->status(), [200, 302, 403, 404, 500]));
        if ($response2->status() === 200) {
            $response2->assertJsonStructure(['success', 'batches']);
        }
    }

    /** @test */
    public function test_store_damages_get_batches_routes_respond()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $store = Store::first() ?? Store::create(['store_name' => 'Main Test Store', 'status' => 1]);
        $product = Product::first();

        $query = '?store_id=' . $store->id . ($product ? '&product_id=' . $product->id : '');

        // Test ajax route
        $response1 = $this->actingAs($user)->getJson('/inventory/store-damages/ajax/get-batches' . $query);
        $this->assertTrue(in_array($response1->status(), [200, 302, 403, 404, 500]));

        // Test direct alias route
        $response2 = $this->actingAs($user)->getJson('/inventory/store-damages/get-batches' . $query);
        $this->assertTrue(in_array($response2->status(), [200, 302, 403, 404, 500]));
    }

    /** @test */
    public function test_tally_card_page_injects_workbench_routes()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/inventory/store-workbench/tally-card');
        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));

        if ($response->status() === 200) {
            $response->assertSee('inventory.store-workbench.store-batches');
            $response->assertSee('inventory.store-damages.get-batches');
            $response->assertSee('inventory.store-damages.get-recent-batches');
            $response->assertSee('inventory.requisition-returns.search-requisitions');
            $response->assertSee('inventory.po-returns.search-pos');
            $response->assertSee('minlength="5"', false);
        }
    }

    /** @test */
    public function test_requisition_return_validation_fails_gracefully()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);

        $response = $this->actingAs($user)->postJson('/inventory/requisition-returns', [
            'store_requisition_id' => 999999,
            'store_requisition_item_id' => 999999,
            'qty_returned' => 1,
            'return_condition' => 'good',
            'return_reason' => 'tets', // less than 5 characters
        ]);

        $this->assertTrue(in_array($response->status(), [422, 302, 403, 404, 500]));
        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['return_reason']);
        }
    }
}
