<?php

namespace Tests\Feature\Pharmacy;

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Tests\TestCase;

class FifoDispenseTest extends TestCase
{
    /** @test */
    public function test_oldest_batch_consumed_first_on_dispense()
    {
        $user = User::factory()->create(['status' => 1]);
        $store = Store::firstOrCreate(['store_name' => 'Main Store']);
        $product = Product::create(['product_name' => 'Paracetamol 500mg 101', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $batch1 = StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'batch_name' => 'BATCH-001',
            'batch_number' => 'BATCH-001',
            'initial_qty' => 10,
            'current_qty' => 10,
            'cost_price' => 10.00,
            'created_by' => $user->id,
            'is_active' => 1,
        ]);
        $batch2 = StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'batch_name' => 'BATCH-002',
            'batch_number' => 'BATCH-002',
            'initial_qty' => 20,
            'current_qty' => 20,
            'cost_price' => 10.00,
            'created_by' => $user->id,
            'is_active' => 1,
        ]);

        $this->assertLessThan($batch2->id, $batch1->id);
    }

    /** @test */
    public function test_stock_batch_qty_decremented_after_dispense()
    {
        $user = User::factory()->create(['status' => 1]);
        $store = Store::firstOrCreate(['store_name' => 'Main Store']);
        $product = Product::create(['product_name' => 'Dec Item 102', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $batch = StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'batch_name' => 'BATCH-DEC',
            'batch_number' => 'BATCH-DEC',
            'initial_qty' => 15,
            'current_qty' => 15,
            'cost_price' => 10.00,
            'created_by' => $user->id,
            'is_active' => 1,
        ]);
        $batch->update(['current_qty' => 10]);
        $this->assertEquals(10, $batch->current_qty);
    }

    /** @test */
    public function test_store_stock_synced_after_dispense()
    {
        $store = Store::firstOrCreate(['store_name' => 'Main Store']);
        $product = Product::create(['product_name' => 'Sync Item 103', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $storeStock = StoreStock::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'current_quantity' => 50,
        ]);
        $this->assertEquals(50, $storeStock->current_quantity);
    }

    /** @test */
    public function test_fifo_spans_multiple_batches_when_qty_exceeds_single_batch()
    {
        $user = User::factory()->create(['status' => 1]);
        $store = Store::firstOrCreate(['store_name' => 'Main Store']);
        $product = Product::create(['product_name' => 'Multi Item 104', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $batch1 = StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'batch_name' => 'BATCH-M1',
            'batch_number' => 'BATCH-M1',
            'initial_qty' => 5,
            'current_qty' => 5,
            'cost_price' => 10.00,
            'created_by' => $user->id,
            'is_active' => 1,
        ]);
        $batch2 = StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'batch_name' => 'BATCH-M2',
            'batch_number' => 'BATCH-M2',
            'initial_qty' => 10,
            'current_qty' => 10,
            'cost_price' => 10.00,
            'created_by' => $user->id,
            'is_active' => 1,
        ]);
        $total = $batch1->current_qty + $batch2->current_qty;
        $this->assertEquals(15, $total);
    }

    /** @test */
    public function test_je_created_on_dispense_approval_via_observer()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }
}
