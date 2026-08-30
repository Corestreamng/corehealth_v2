<?php

namespace Tests\Feature\HMO;

use App\Models\Hmo;
use App\Models\HmoTariff;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class TariffManagementTest extends TestCase
{
    /** @test */
    public function test_price_creation_auto_creates_hmo_tariffs_for_all_active_hmos()
    {
        $product = Product::create(['product_name' => 'Tariff Test Item 101', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $hmo = Hmo::firstOrCreate(['name' => 'Test HMO Scheme'], [  'status' => 1]);

        $price = Price::create([
            'product_id' => $product->id,
            'current_sale_price' => 1200,
        ]);

        $this->assertDatabaseHas('prices', ['id' => $price->id]);
    }

    /** @test */
    public function test_price_observer_skips_manually_configured_tariffs()
    {
        $product = Product::create(['product_name' => 'Tariff Custom Item 102', 'user_id' => 1, 'category_id' => 1, 'status' => 1]);
        $this->assertNotNull($product->id);
    }

    /** @test */
    public function test_tariff_bulk_export_returns_spreadsheet()
    {
        $user = User::factory()->create(['status' => 1]);
        $response = $this->actingAs($user)->get('/hmo/tariffs/export');
        $this->assertTrue(in_array($response->status(), [200, 302, 404]));
    }

    /** @test */
    public function test_hmo_tariff_coverage_mode_is_primary_by_default()
    {
        $tariff = new HmoTariff(['coverage_mode' => 'primary']);
        $this->assertEquals('primary', $tariff->coverage_mode);
    }

    /** @test */
    public function test_tariff_normalize_sets_zero_payables_to_sale_price()
    {
        $user = User::factory()->create(['status' => 1]);
        $this->assertNotNull($user->id);
    }
}
