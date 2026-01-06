<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\Hmo;
use App\Models\HmoTariff;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     * Automatically generates tariff entries for all existing HMOs.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    public function created(Product $product)
    {
        try {
            $hmos = Hmo::where('status', 1)->get();

            if ($hmos->isEmpty()) {
                return;
            }

            $tariffs = [];
            $price = $product->price ? $product->price->current_sale_price : 0;

            foreach ($hmos as $hmo) {
                // Check if tariff already exists
                $exists = HmoTariff::where('hmo_id', $hmo->id)
                    ->where('product_id', $product->id)
                    ->whereNull('service_id')
                    ->exists();

                if (!$exists) {
                    $tariffs[] = [
                        'hmo_id' => $hmo->id,
                        'product_id' => $product->id,
                        'service_id' => null,
                        'claims_amount' => 0,
                        'payable_amount' => $price,
                        'coverage_mode' => 'primary',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($tariffs)) {
                HmoTariff::insert($tariffs);
                Log::info("Created " . count($tariffs) . " tariff entries for product: " . $product->id);
            }
        } catch (\Exception $e) {
            Log::error("Failed to create tariffs for product {$product->id}: " . $e->getMessage());
        }
    }
}
