<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\StockBatch;
use App\Models\StockBatchTransaction;
use App\Models\StockUtilization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockUtilizationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $store;
    protected $category;
    protected $product;

    protected function setUp(): void
    {
        // Intercept PHPUnit's sqlite overrides and force mysql for testing environment
        $_ENV['DB_CONNECTION'] = 'mysql';
        $_SERVER['DB_CONNECTION'] = 'mysql';
        $_ENV['DB_DATABASE'] = '_corehealth_db_v2_test';
        $_SERVER['DB_DATABASE'] = '_corehealth_db_v2_test';

        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', '_corehealth_db_v2_test');

        // Setup base entities using Eloquent (migrations run automatically via RefreshDatabase)
        $this->user = User::create([
            'surname' => 'Admin',
            'firstname' => 'User',
            'email' => 'admin@corehealth.com',
            'password' => bcrypt('password'),
            'is_admin' => 1,
            'status' => 1
        ]);

        $this->store = Store::create([
            'store_name' => 'Main Pharmacy Store',
            'status' => 1
        ]);

        $this->category = ProductCategory::create([
            'category_name' => 'Consumables'
        ]);

        $this->product = Product::create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id,
            'product_name' => 'A4 Paper Pack',
            'product_code' => 'A4-PAP',
            'status' => 1
        ]);

        // Create a price record for the product
        \DB::table('prices')->insert([
            'product_id' => $this->product->id,
            'current_sale_price' => 50.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_can_retrieve_available_products_for_a_store()
    {
        $this->actingAs($this->user);

        // Associate product with store stock
        StoreStock::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'initial_quantity' => 10,
            'current_quantity' => 10,
            'reorder_level' => 2,
            'is_active' => true
        ]);

        $response = $this->getJson(route('inventory.requisitions.my-stock.products', [
            'store_id' => $this->store->id
        ]));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'product_id' => $this->product->id,
                'current_quantity' => 10
            ]);
    }

    /** @test */
    public function it_can_retrieve_active_batches_for_a_product()
    {
        $this->actingAs($this->user);

        // Create batches
        StockBatch::create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'batch_name' => 'First Batch',
            'batch_number' => 'B-001',
            'initial_qty' => 50,
            'current_qty' => 50,
            'cost_price' => 12.00,
            'expiry_date' => now()->addYear()->toDateString(),
            'created_by' => $this->user->id
        ]);

        $response = $this->getJson(route('inventory.requisitions.my-stock.batches', [
            'store_id' => $this->store->id,
            'product_id' => $this->product->id
        ]));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'batch_number' => 'B-001',
                'current_qty' => 50
            ]);
    }

    /** @test */
    public function it_can_log_internal_utilization_with_fefo_order()
    {
        $this->actingAs($this->user);

        // Establish stock with 2 batches (one expiring sooner)
        $batchLong = StockBatch::create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'batch_name' => 'Long Expiry Batch',
            'batch_number' => 'B-LONG',
            'initial_qty' => 10,
            'current_qty' => 10,
            'cost_price' => 10.00,
            'expiry_date' => now()->addDays(30)->toDateString(),
            'created_by' => $this->user->id
        ]);

        $batchShort = StockBatch::create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'batch_name' => 'Short Expiry Batch',
            'batch_number' => 'B-SHORT',
            'initial_qty' => 5,
            'current_qty' => 5,
            'cost_price' => 10.00,
            'expiry_date' => now()->addDays(5)->toDateString(),
            'created_by' => $this->user->id
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'initial_quantity' => 15,
            'current_quantity' => 15,
            'is_active' => true
        ]);

        $payload = [
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'qty' => 8,
            'unit' => 'pack',
            'reason' => 'Department Stationary',
            'utilization_type' => 'internal',
            'strategy' => 'fefo',
            'start_date' => now()->subWeeks(3)->toDateTimeString(),
            'end_date' => now()->toDateTimeString()
        ];

        $response = $this->postJson(route('inventory.requisitions.my-stock.utilize'), $payload);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        // Assert short batch is completely depleted, and long batch is partially depleted
        $this->assertEquals(0, $batchShort->fresh()->current_qty);
        $this->assertEquals(7, $batchLong->fresh()->current_qty);

        $this->assertDatabaseHas('stock_utilizations', [
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'qty' => 8,
            'utilization_type' => 'internal',
            'reason' => 'Department Stationary'
        ]);
    }

    /** @test */
    public function it_can_log_patient_utilization_with_direct_billing()
    {
        $this->actingAs($this->user);

        // Setup patient
        $patientUser = User::create([
            'surname' => 'Doe',
            'firstname' => 'John',
            'email' => 'john.doe@example.com',
            'password' => bcrypt('password')
        ]);
        $patient = Patient::create([
            'user_id' => $patientUser->id,
            'file_no' => 'FN-JOHN-001'
        ]);

        // Setup stock
        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'batch_name' => 'Batch A',
            'batch_number' => 'B-A',
            'initial_qty' => 10,
            'current_qty' => 10,
            'cost_price' => 10.00,
            'expiry_date' => now()->addDays(30)->toDateString(),
            'created_by' => $this->user->id
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'initial_quantity' => 10,
            'current_quantity' => 10,
            'is_active' => true
        ]);

        $payload = [
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'unit' => 'pcs',
            'reason' => 'Clinical Treatment',
            'utilization_type' => 'patient',
            'patient_id' => $patient->id,
            'is_billed' => true,
            'strategy' => 'fifo'
        ];

        $response = $this->postJson(route('inventory.requisitions.my-stock.utilize'), $payload);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true]);

        // Verify batch deduction
        $this->assertEquals(8, $batch->fresh()->current_qty);

        // Verify billing request was created
        $this->assertDatabaseHas('product_or_service_requests', [
            'user_id' => $patientUser->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'payable_amount' => 100.00, // 50.00 * 2
            'claims_amount' => 0.00,
            'coverage_mode' => 'none'
        ]);

        // Verify prescription record created
        $this->assertDatabaseHas('product_requests', [
            'patient_id' => $patient->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'status' => 3
        ]);
    }
}
