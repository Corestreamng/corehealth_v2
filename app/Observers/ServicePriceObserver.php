<?php

namespace App\Observers;

use App\Models\ServicePrice;
use App\Models\HmoTariff;
use Illuminate\Support\Facades\Log;

class ServicePriceObserver
{
    /**
     * Handle the ServicePrice "created" event.
     * Updates all HMO tariffs for this service with the new price as payable_amount.
     *
     * @param  \App\Models\ServicePrice  $servicePrice
     * @return void
     */
    public function created(ServicePrice $servicePrice)
    {
        $this->updateTariffsWithPrice($servicePrice);
    }

    /**
     * Update all HMO tariffs for a service with the new price.
     * Only updates tariffs that have payable_amount = 0 (not manually configured).
     * This only runs on price CREATION, not updates, to preserve HMO executive changes.
     *
     * @param  \App\Models\ServicePrice  $servicePrice
     * @return void
     */
    protected function updateTariffsWithPrice(ServicePrice $servicePrice)
    {
        try {
            $price = $servicePrice->sale_price ?? 0;

            // Update tariffs that have payable_amount = 0 (default/unconfigured)
            $updated = HmoTariff::where('service_id', $servicePrice->service_id)
                ->whereNull('product_id')
                ->where('payable_amount', 0)
                ->update(['payable_amount' => $price]);

            if ($updated > 0) {
                Log::info("Updated {$updated} HMO tariffs for service {$servicePrice->service_id} with price {$price}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to update tariffs for service {$servicePrice->service_id}: " . $e->getMessage());
        }
    }
}
