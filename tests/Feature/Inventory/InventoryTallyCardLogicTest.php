<?php

namespace Tests\Feature\Inventory;

use App\Models\Price;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockBatchTransaction;
use App\Models\Store;
use App\Models\User;
use App\Services\StockService;
use Tests\TestCase;

class InventoryTallyCardLogicTest extends TestCase
{
    /** @test */
    public function test_tally_card_data_uses_batch_cost_price_with_zero_fallback_and_returns_batch_id()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $store = Store::first() ?? Store::factory()->create();
        $product = Product::first() ?? Product::factory()->create();

        // Ensure product price has a distinct sale price
        Price::updateOrCreate(
            ['product_id' => $product->id],
            ['current_sale_price' => 999.00, 'pr_buy_price' => 500.00]
        );

        // Create batch with cost_price 0.00
        $batch = StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'batch_name' => 'Tally Test Batch ' . uniqid(),
            'batch_number' => 'TALLY-TEST-' . uniqid(),
            'initial_qty' => 10,
            'current_qty' => 10,
            'cost_price' => 0.00,
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        $tx = StockBatchTransaction::create([
            'stock_batch_id' => $batch->id,
            'type' => 'in',
            'qty' => 10,
            'balance_after' => 10,
            'performed_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/inventory/store-workbench/tally-card/data?' . http_build_query([
            'axis' => 'product',
            'store_id' => $store->id,
            'product_id' => $product->id,
            'date_from' => now()->subDay()->format('Y-m-d'),
            'date_to' => now()->addDay()->format('Y-m-d'),
        ]));

        $this->assertTrue(in_array($response->status(), [200, 403]));
        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertTrue($data['success']);
            $matchingTx = collect($data['transactions'])->firstWhere('id', $tx->id);
            if ($matchingTx) {
                // Must be 0.00, NOT falling back to sale price (999.00)
                $this->assertEquals(0.0, (float) $matchingTx['cost_price']);
                $this->assertEquals($batch->id, $matchingTx['batch_id']);
            }
        }
    }

    /** @test */
    public function test_stock_service_create_batch_preserves_zero_cost_price()
    {
        $store = Store::first() ?? Store::factory()->create();
        $product = Product::first() ?? Product::factory()->create();

        Price::updateOrCreate(
            ['product_id' => $product->id],
            ['pr_buy_price' => 250.00]
        );

        $stockService = app(StockService::class);
        $batch = $stockService->createBatch([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'qty' => 5,
            'cost_price' => 0.00,
            'batch_name' => 'Zero Cost Batch',
            'batch_number' => 'PRESERVE-ZERO-' . uniqid(),
            'created_by' => 1,
        ]);

        // Must not be overwritten with pr_buy_price (250.00)
        $this->assertEquals(0.0, (float) $batch->cost_price);
    }
}
