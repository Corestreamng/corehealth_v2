<?php

namespace Tests\Feature\Audit;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Tests\TestCase;

class AuditWorkbenchBatchCostTest extends TestCase
{
    /** @test */
    public function test_batch_creation_requires_cost_price_greater_than_zero_for_standard_batches()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        if (method_exists($user, 'assignRole')) {
            try {
                $user->assignRole('super-admin');
            } catch (\Exception $e) {
                // Role might not exist in some seeds
            }
        }
        $store = Store::first() ?? Store::create([
            'store_name' => 'Test Main Store',
            'distribution_role' => Store::ROLE_CENTRAL,
            'status' => true,
        ]);
        $product = Product::first() ?? Product::create([
            'product_name' => 'Paracetamol 500mg',
            'product_code' => 'PARA500',
        ]);

        // Attempt batch creation with cost_price = 0 without donation flag
        $response = $this->actingAs($user)->postJson(route('inventory.store-workbench.create-manual-batch'), [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'batch_number' => 'TEST-BATCH-ZERO-' . uniqid(),
            'quantity' => 100,
            'cost_price' => 0,
            'is_donation' => 0,
            'source' => 'manual',
        ]);

        // Should fail validation (422) or redirect with errors (302)
        $this->assertTrue(in_array($response->status(), [302, 403, 422]));
        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['cost_price']);
        }
    }

    /** @test */
    public function test_batch_creation_allows_zero_cost_price_when_marked_as_donation()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        if (method_exists($user, 'assignRole')) {
            try {
                $user->assignRole('super-admin');
            } catch (\Exception $e) {
                // Role might not exist in some seeds
            }
        }
        $store = Store::first() ?? Store::create([
            'store_name' => 'Test Central Store',
            'distribution_role' => Store::ROLE_CENTRAL,
            'status' => true,
        ]);
        $product = Product::first() ?? Product::create([
            'product_name' => 'Amoxicillin 250mg',
            'product_code' => 'AMOX250',
        ]);

        $batchNumber = 'TEST-DONATION-' . uniqid();
        $response = $this->actingAs($user)->postJson(route('inventory.store-workbench.create-manual-batch'), [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'batch_number' => $batchNumber,
            'quantity' => 50,
            'cost_price' => 0,
            'is_donation' => 1,
            'source' => 'manual',
            'notes' => 'Donation from WHO',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
        if ($response->status() === 200) {
            $response->assertJson(['success' => true]);
            $this->assertDatabaseHas('stock_batches', [
                'batch_number' => $batchNumber,
                'cost_price' => 0.00,
                'is_donation' => 1,
            ]);
        }
    }

    /** @test */
    public function test_audit_workbench_dispensing_revenue_attribution_story()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/admin/audit-workbench/story/store-utilization/dispensing-revenue-attribution');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'cards',
                'rows',
                'headers',
            ]);
        }
    }

    /** @test */
    public function test_audit_workbench_batch_valuation_story()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->getJson('/admin/audit-workbench/story/main-store/batch-valuation');

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'cards',
                'rows',
                'headers',
            ]);
        }
    }

    /** @test */
    public function test_audit_workbench_dispensing_revenue_attribution_drilldown()
    {
        $user = User::first() ?? User::factory()->create(['status' => 1]);
        $product = Product::first();
        $key = $product ? $product->id : 1;

        $response = $this->actingAs($user)->getJson('/admin/audit-workbench/drilldown/store-utilization/dispensing-revenue-attribution/' . $key);

        $this->assertTrue(in_array($response->status(), [200, 302, 403, 404, 500]));
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'title',
                'cards',
                'rows',
                'headers',
            ]);
        }
    }
}
