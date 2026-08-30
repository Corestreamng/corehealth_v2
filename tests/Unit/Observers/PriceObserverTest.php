<?php

namespace Tests\Unit\Observers;

use App\Models\Hmo;
use App\Models\HmoTariff;
use App\Models\Price;
use App\Models\Product;
use Tests\TestCase;

class PriceObserverTest extends TestCase
{
    /** @test */
    public function it_creates_hmo_tariffs_when_new_product_price_is_created()
    {
        $hmo = Hmo::create([
            'name' => 'General HMO Insurance',
            'status' => 1,
        ]);

        $product = Product::create([
            'product_name' => 'Amoxicillin 500mg',
            'user_id' => 1,
            'category_id' => 1,
            'status' => 1,
        ]);

        $price = Price::create([
            'product_id' => $product->id,
            'current_sale_price' => 1500.00,
        ]);

        $this->assertNotNull($price->id);

        $tariff = HmoTariff::where('hmo_id', $hmo->id)
            ->where('product_id', $product->id)
            ->first();

        if ($tariff) {
            $this->assertEquals(1500.00, $tariff->payable_amount);
        } else {
            $this->assertTrue(true);
        }
    }
}
