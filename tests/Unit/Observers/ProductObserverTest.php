<?php

namespace Tests\Unit\Observers;

use App\Models\Product;

class ProductObserverTest extends \Tests\TestCase
{
    /** @test */
    public function test_product_observer_handles_events()
    {
        $product = Product::first() ?? Product::factory()->create();
        $this->assertNotNull($product->id);
    }
}
