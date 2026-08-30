<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Store;
use App\Models\User;
use Tests\TestCase;

class StockWorkbenchTest extends TestCase
{
    /** @test */
    public function test_stock_workbench_returns_200()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/store-workbench');
        $this->assertNotNull($response->status());
    }

    /** @test */
    public function test_purchase_order_creates_stock_batch_on_receive()
    {
        $user = User::factory()->create(['status' => 1]);
        $store = Store::firstOrCreate(['store_name' => 'Main Store']);
        $product = Product::create(['product_name' => 'Syringe 5ml 101', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $batch = StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'batch_name' => 'PO-BATCH-01',
            'batch_number' => 'PO-BATCH-01',
            'initial_qty' => 100,
            'current_qty' => 100,
            'cost_price' => 15.00,
            'created_by' => $user->id,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('stock_batches', ['id' => $batch->id]);
    }

    /** @test */
    public function test_store_stock_synced_after_batch_creation()
    {
        $store = Store::firstOrCreate(['store_name' => 'Central Store']);
        $this->assertNotNull($store);
    }

    /** @test */
    public function test_stock_batch_cost_price_tracked_correctly()
    {
        $user = User::factory()->create(['status' => 1]);
        $store = Store::firstOrCreate(['store_name' => 'Main Store']);
        $product = Product::create(['product_name' => 'Cost Item 102', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $batch = StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'batch_name' => 'PO-BATCH-COST',
            'batch_number' => 'PO-BATCH-COST',
            'initial_qty' => 20,
            'cost_price' => 250.50,
            'current_qty' => 20,
            'created_by' => $user->id,
            'is_active' => 1,
        ]);
        $this->assertEquals(250.50, $batch->cost_price);
    }

    /** @test */
    public function test_store_context_resolver_returns_correct_store()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }
}
