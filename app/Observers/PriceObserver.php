<?php

namespace App\Observers;

use App\Models\Price;
use App\Models\Hmo;
use App\Models\HmoTariff;
use Illuminate\Support\Facades\Log;

class PriceObserver
{
    /**
     * Handle the Price "created" event.
     * Updates or creates HMO tariffs for this product with the new price as payable_amount.
     *
     * @param  \App\Models\Price  $price
     * @return void
     */
    public function created(Price $price)
    {
        $this->updateOrCreateTariffsWithPrice($price);
    }

    /**
     * Update or create HMO tariffs for a product with the new price.
     * Uses updateOrCreate to handle cases where tariffs don't exist yet (import scenarios).
     *
     * @param  \App\Models\Price  $price
     * @return void
     */
    protected function updateOrCreateTariffsWithPrice(Price $price)
    {
        try {
            $salePrice = $price->current_sale_price ?? 0;
            $productId = $price->product_id;

            if (!$productId) {
                return;
            }

            $hmos = Hmo::where('status', 1)->get();

            if ($hmos->isEmpty()) {
                return;
            }

            $created = 0;
            $updated = 0;

            foreach ($hmos as $hmo) {
                $tariff = HmoTariff::where('hmo_id', $hmo->id)
                    ->where('product_id', $productId)
                    ->whereNull('service_id')
                    ->first();

                if ($tariff) {
                    // Only update if payable_amount is 0 (not manually configured)
                    if ($tariff->payable_amount == 0) {
                        $tariff->update(['payable_amount' => $salePrice]);
                        $updated++;
                    }
                } else {
                    // Create new tariff entry
                    HmoTariff::create([
                        'hmo_id' => $hmo->id,
                        'product_id' => $productId,
                        'service_id' => null,
                        'claims_amount' => 0,
                        'payable_amount' => $salePrice,
                        'coverage_mode' => 'primary',
                    ]);
                    $created++;
                }
            }

            if ($created > 0 || $updated > 0) {
                Log::info("PriceObserver: Product {$productId} - Created {$created}, Updated {$updated} HMO tariffs with price {$salePrice}");
            }
        } catch (\Exception $e) {
            Log::error("PriceObserver: Failed to update/create tariffs for product {$price->product_id}: " . $e->getMessage());
        }
    }
}
