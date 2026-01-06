<?php

namespace App\Observers;

use App\Models\Hmo;
use App\Models\Product;
use App\Models\Service;
use App\Models\HmoTariff;
use Illuminate\Support\Facades\Log;

class HmoObserver
{
    /**
     * Handle the Hmo "created" event.
     * Automatically generates tariff entries for all existing products and services.
     *
     * @param  \App\Models\Hmo  $hmo
     * @return void
     */
    public function created(Hmo $hmo)
    {
        try {
            $tariffs = [];

            // Generate tariffs for all products
            $products = Product::all();
            foreach ($products as $product) {
                // Check if tariff already exists
                $exists = HmoTariff::where('hmo_id', $hmo->id)
                    ->where('product_id', $product->id)
                    ->whereNull('service_id')
                    ->exists();

                if (!$exists) {
                    $price = $product->price ? $product->price->current_sale_price : 0;
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

            // Generate tariffs for all services
            $services = Service::all();
            foreach ($services as $service) {
                // Check if tariff already exists
                $exists = HmoTariff::where('hmo_id', $hmo->id)
                    ->where('service_id', $service->id)
                    ->whereNull('product_id')
                    ->exists();

                if (!$exists) {
                    $price = $service->price ? $service->price->sale_price : 0;
                    $tariffs[] = [
                        'hmo_id' => $hmo->id,
                        'product_id' => null,
                        'service_id' => $service->id,
                        'claims_amount' => 0,
                        'payable_amount' => $price,
                        'coverage_mode' => 'primary',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Use chunking for large datasets
            if (!empty($tariffs)) {
                foreach (array_chunk($tariffs, 500) as $chunk) {
                    HmoTariff::insert($chunk);
                }
                Log::info("Created " . count($tariffs) . " tariff entries for HMO: " . $hmo->id);
            }
        } catch (\Exception $e) {
            Log::error("Failed to create tariffs for HMO {$hmo->id}: " . $e->getMessage());
        }
    }
}
