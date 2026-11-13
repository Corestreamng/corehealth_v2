<?php

namespace Tests\Feature\HMO;

use App\Helpers\HmoHelper;
use App\Models\Hmo;
use App\Models\HmoScheme;
use App\Models\HmoTariff;
use App\Models\Patient;
use App\Models\Price;
use App\Models\Product;
use App\Models\TariffOverride;
use App\Models\User;
use Tests\TestCase;

class TariffOverrideTest extends TestCase
{
    /** @test */
    public function test_tariff_override_can_be_stored_with_coverage_mode()
    {
        $admin = User::find(1) ?? User::factory()->create(['status' => 1]);
        $scheme = HmoScheme::first() ?? HmoScheme::create(['name' => 'Test Scheme', 'status' => 1]);

        $response = $this->actingAs($admin)->postJson(route('hmo-tariffs.overrides.store'), [
            'context_type' => 'scheme',
            'context_id' => $scheme->id,
            'target_type' => 'all_products',
            'target_id' => 0,
            'override_type' => 'percentage',
            'amount' => 0,
            'coverage_mode' => 'secondary',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('tariff_overrides', [
            'hmo_scheme_id' => $scheme->id,
            'target_type' => 'all_products',
            'amount' => 0,
            'coverage_mode' => 'secondary',
        ]);
    }

    /** @test */
    public function test_tariff_override_get_overrides_returns_coverage_mode()
    {
        $admin = User::find(1) ?? User::factory()->create(['status' => 1]);
        $scheme = HmoScheme::first() ?? HmoScheme::create(['name' => 'Test Scheme', 'status' => 1]);

        TariffOverride::create([
            'hmo_scheme_id' => $scheme->id,
            'target_type' => 'all_products',
            'target_id' => 0,
            'override_type' => 'percentage',
            'amount' => 0,
            'coverage_mode' => 'primary',
            'is_active' => 1,
        ]);

        $response = $this->actingAs($admin)->getJson(route('hmo-tariffs.overrides.data'));
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $found = collect($data)->firstWhere('target_name', 'All Products');
        $this->assertNotNull($found);
        $this->assertEquals('Primary', $found['coverage_mode']);
        $this->assertEquals('primary', $found['raw_coverage_mode']);
    }

    /** @test */
    public function test_hmo_helper_applies_all_products_override_at_scheme_level()
    {
        $scheme = HmoScheme::create(['name' => 'Corporate Test Scheme ' . uniqid(), 'code' => 'SCH_' . uniqid(), 'status' => 1]);
        $hmo = Hmo::create(['name' => 'Corporate Test HMO ' . uniqid(), 'hmo_scheme_id' => $scheme->id, 'status' => 1]);
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::create(['user_id' => $user->id, 'hmo_id' => $hmo->id]);

        $product = Product::create([
            'product_name' => 'Override Drug ' . uniqid(),
            'user_id' => $user->id,
            'category_id' => 1,
            'status' => 1,
        ]);
        Price::create([
            'product_id' => $product->id,
            'current_sale_price' => 1500,
        ]);

        // Create All Products override: 0% payable, explicit secondary coverage mode
        TariffOverride::create([
            'hmo_scheme_id' => $scheme->id,
            'target_type' => 'all_products',
            'target_id' => 0,
            'override_type' => 'percentage',
            'amount' => 0,
            'coverage_mode' => 'secondary',
            'is_active' => 1,
        ]);

        $result = HmoHelper::applyHmoTariff($patient->id, $product->id, null);

        $this->assertNotNull($result);
        $this->assertEquals(0.00, $result['payable_amount'], 'Patient payable must be 0 for 0% override');
        $this->assertEquals(1500.00, $result['claims_amount'], 'HMO claim should cover full price');
        $this->assertEquals('secondary', $result['coverage_mode'], 'Coverage mode must match explicit override');
        $this->assertEquals('pending', $result['validation_status'], 'Secondary coverage mode requires pending validation');
    }

    /** @test */
    public function test_hmo_helper_inherits_underlying_tariff_coverage_mode_when_override_mode_is_null()
    {
        $scheme = HmoScheme::create(['name' => 'Inherit Scheme ' . uniqid(), 'code' => 'SCH_' . uniqid(), 'status' => 1]);
        $hmo = Hmo::create(['name' => 'Inherit HMO ' . uniqid(), 'hmo_scheme_id' => $scheme->id, 'status' => 1]);
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::create(['user_id' => $user->id, 'hmo_id' => $hmo->id]);

        $product = Product::create([
            'product_name' => 'Inherit Drug ' . uniqid(),
            'user_id' => $user->id,
            'category_id' => 1,
            'status' => 1,
        ]);
        Price::create([
            'product_id' => $product->id,
            'current_sale_price' => 2000,
        ]);

        // Explicit underlying tariff with express coverage mode (update the one auto-created by PriceObserver)
        HmoTariff::updateOrCreate(
            ['hmo_id' => $hmo->id, 'product_id' => $product->id],
            [
                'payable_amount' => 500,
                'claims_amount' => 1500,
                'coverage_mode' => 'express',
            ]
        );

        // Override with null coverage mode (inherit)
        TariffOverride::create([
            'hmo_scheme_id' => $scheme->id,
            'target_type' => 'all_products',
            'target_id' => 0,
            'override_type' => 'percentage',
            'amount' => 10,
            'coverage_mode' => null,
            'is_active' => 1,
        ]);

        $result = HmoHelper::applyHmoTariff($patient->id, $product->id, null);

        $this->assertNotNull($result);
        $this->assertEquals(200.00, $result['payable_amount'], '10% of 2000 is 200');
        $this->assertEquals(1800.00, $result['claims_amount']);
        $this->assertEquals('express', $result['coverage_mode'], 'Should inherit express from underlying tariff');
        $this->assertEquals('approved', $result['validation_status'], 'Express mode auto-approves validation status');
    }

    /** @test */
    public function test_override_hierarchy_exact_product_beats_all_products()
    {
        $scheme = HmoScheme::create(['name' => 'Hierarchy Scheme ' . uniqid(), 'code' => 'SCH_' . uniqid(), 'status' => 1]);
        $hmo = Hmo::create(['name' => 'Hierarchy HMO ' . uniqid(), 'hmo_scheme_id' => $scheme->id, 'status' => 1]);
        $user = User::factory()->create(['status' => 1]);
        $patient = Patient::create(['user_id' => $user->id, 'hmo_id' => $hmo->id]);

        $product = Product::create([
            'product_name' => 'Hierarchy Drug ' . uniqid(),
            'user_id' => $user->id,
            'category_id' => 1,
            'status' => 1,
        ]);
        Price::create([
            'product_id' => $product->id,
            'current_sale_price' => 1000,
        ]);

        // Broad Scheme override: All Products at 0%
        TariffOverride::create([
            'hmo_scheme_id' => $scheme->id,
            'target_type' => 'all_products',
            'target_id' => 0,
            'override_type' => 'percentage',
            'amount' => 0,
            'coverage_mode' => 'express',
            'is_active' => 1,
        ]);

        // Specific Item override: 50% payable, primary coverage mode
        TariffOverride::create([
            'hmo_scheme_id' => $scheme->id,
            'target_type' => 'product',
            'target_id' => $product->id,
            'override_type' => 'percentage',
            'amount' => 50,
            'coverage_mode' => 'primary',
            'is_active' => 1,
        ]);

        $result = HmoHelper::applyHmoTariff($patient->id, $product->id, null);

        $this->assertNotNull($result);
        $this->assertEquals(500.00, $result['payable_amount'], 'Specific product override must beat all_products');
        $this->assertEquals('primary', $result['coverage_mode'], 'Specific product coverage_mode must beat all_products');
    }
}
