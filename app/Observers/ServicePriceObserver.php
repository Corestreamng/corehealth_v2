<?php

namespace App\Observers;

use App\Models\ServicePrice;
use App\Models\Hmo;
use App\Models\HmoTariff;
use Illuminate\Support\Facades\Log;

class ServicePriceObserver
{
    /**
     * Handle the ServicePrice "created" event.
     * Updates or creates HMO tariffs for this service with the new price as payable_amount.
     *
     * @param  \App\Models\ServicePrice  $servicePrice
     * @return void
     */
    public function created(ServicePrice $servicePrice)
    {
        $this->updateOrCreateTariffsWithPrice($servicePrice);
    }

    /**
     * Update or create HMO tariffs for a service with the new price.
     * Uses updateOrCreate to handle cases where tariffs don't exist yet (import scenarios).
     *
     * @param  \App\Models\ServicePrice  $servicePrice
     * @return void
     */
    protected function updateOrCreateTariffsWithPrice(ServicePrice $servicePrice)
    {
        try {
            $price = $servicePrice->sale_price ?? 0;
            $serviceId = $servicePrice->service_id;

            if (!$serviceId) {
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
                    ->where('service_id', $serviceId)
                    ->whereNull('product_id')
                    ->first();

                if ($tariff) {
                    // Only update if payable_amount is 0 (not manually configured)
                    if ($tariff->payable_amount == 0) {
                        $tariff->update(['payable_amount' => $price]);
                        $updated++;
                    }
                } else {
                    // Create new tariff entry
                    HmoTariff::create([
                        'hmo_id' => $hmo->id,
                        'product_id' => null,
                        'service_id' => $serviceId,
                        'claims_amount' => 0,
                        'payable_amount' => $price,
                        'coverage_mode' => 'primary',
                    ]);
                    $created++;
                }
            }

            if ($created > 0 || $updated > 0) {
                Log::info("ServicePriceObserver: Service {$serviceId} - Created {$created}, Updated {$updated} HMO tariffs with price {$price}");
            }
        } catch (\Exception $e) {
            Log::error("ServicePriceObserver: Failed to update/create tariffs for service {$servicePrice->service_id}: " . $e->getMessage());
        }
    }
}
