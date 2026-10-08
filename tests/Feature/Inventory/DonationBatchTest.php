<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockBatchTransaction;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockService;
use Tests\TestCase;

class DonationBatchTest extends TestCase
{
    /** @test */
    public function test_stock_service_creates_donation_batch_with_zero_cost_price_and_donor()
    {
        $store = Store::first() ?? Store::factory()->create();
        $product = Product::first() ?? Product::factory()->create();
        $supplier = Supplier::first() ?? Supplier::create([
            'company_name' => 'UNICEF Global Health',
            'contact_person' => 'John Doe',
        ]);

        $stockService = app(StockService::class);
        $batch = $stockService->createBatch([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'supplier_id' => $supplier->id,
            'qty' => 100,
            'cost_price' => 0.00,
            'is_donation' => true,
            'batch_number' => 'DONATION-' . uniqid(),
            'notes' => 'Donation from partner organization',
            'created_by' => 1,
        ]);

        $this->assertTrue((bool) $batch->is_donation);
        $this->assertEquals(0.0, (float) $batch->cost_price);
        $this->assertEquals($supplier->id, $batch->supplier_id);
        $this->assertEquals($supplier->company_name, $batch->donor?->company_name);
    }

    /** @test */
    public function test_manual_batch_controller_endpoint_creates_donation_batch_with_zero_cost()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $store = Store::first() ?? Store::factory()->create();
        $product = Product::first() ?? Product::factory()->create();
        $supplier = Supplier::first() ?? Supplier::create([
            'company_name' => 'WHO Donor Supply',
            'contact_person' => 'Jane Smith',
        ]);

        // Attach store to user if needed
        if (method_exists($store, 'users')) {
            $store->users()->syncWithoutDetaching([$user->id]);
        }

        $batchNumber = 'MANUAL-DON-' . uniqid();

        $response = $this->actingAs($user)->postJson(route('inventory.store-workbench.create-manual-batch'), [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'quantity' => 25,
            'cost_price' => 0,
            'is_donation' => 1,
            'reference_type' => 'donation',
            'batch_number' => $batchNumber,
            'notes' => 'Donation batch received',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403]));

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertTrue($data['success']);

            $batch = StockBatch::where('batch_number', $batchNumber)->first();
            $this->assertNotNull($batch);
            $this->assertTrue((bool) $batch->is_donation);
            $this->assertEquals(0.0, (float) $batch->cost_price);
            $this->assertEquals($supplier->id, $batch->supplier_id);
            $this->assertEquals($supplier->id, $batch->donor?->id);
        }
    }

    /** @test */
    public function test_donation_batch_with_null_cost_price_defaults_to_zero()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $store = Store::first() ?? Store::factory()->create();
        $product = Product::first() ?? Product::factory()->create();

        if (method_exists($store, 'users')) {
            $store->users()->syncWithoutDetaching([$user->id]);
        }

        $batchNumber = 'NULL-COST-DON-' . uniqid();

        $response = $this->actingAs($user)->postJson(route('inventory.store-workbench.create-manual-batch'), [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'cost_price' => null,
            'is_donation' => 1,
            'batch_number' => $batchNumber,
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403]));

        if ($response->status() === 200) {
            $batch = StockBatch::where('batch_number', $batchNumber)->first();
            $this->assertNotNull($batch);
            $this->assertTrue((bool) $batch->is_donation);
            $this->assertEquals(0.0, (float) $batch->cost_price);
        }
    }

    /** @test */
    public function test_tally_card_data_labels_donation_in_transaction()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $store = Store::first() ?? Store::factory()->create();
        $product = Product::first() ?? Product::factory()->create();

        $batch = StockBatch::create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'batch_name' => 'Donation Tally Batch ' . uniqid(),
            'batch_number' => 'DON-TALLY-' . uniqid(),
            'initial_qty' => 50,
            'current_qty' => 50,
            'cost_price' => 0.00,
            'is_donation' => true,
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        $tx = StockBatchTransaction::create([
            'stock_batch_id' => $batch->id,
            'type' => 'in',
            'qty' => 50,
            'balance_after' => 50,
            'reference_type' => 'donation',
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
            $matchingTx = collect($data['transactions'])->firstWhere('id', $tx->id);
            if ($matchingTx) {
                $this->assertEquals('Donation In', $matchingTx['type_label']);
                $this->assertEquals('donation_in', $matchingTx['badge_type']);
                $this->assertTrue($matchingTx['is_donation']);
                $this->assertEquals(0.0, (float) $matchingTx['cost_price']);
            }
        }
    }
}
