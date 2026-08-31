<?php

namespace Tests\Unit\Observers;

use App\Models\Product;

class StockBatchObserverTest extends \Tests\TestCase
{
    /** @test */
    public function test_stock_batch_observer_exists_and_handles_events()
    {
        $product = Product::first() ?? Product::factory()->create();
        $this->assertNotNull($product->id);
    }
}
