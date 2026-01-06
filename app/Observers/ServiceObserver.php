<?php

namespace App\Observers;

use App\Models\Service;
use App\Models\Hmo;
use App\Models\HmoTariff;
use Illuminate\Support\Facades\Log;

class ServiceObserver
{
    /**
     * Handle the Service "created" event.
     * Automatically generates tariff entries for all existing HMOs.
     *
     * @param  \App\Models\Service  $service
     * @return void
     */
    public function created(Service $service)
    {
        try {
            $hmos = Hmo::where('status', 1)->get();

            if ($hmos->isEmpty()) {
                return;
            }

            $tariffs = [];
            $price = $service->price ? $service->price->sale_price : 0;

            foreach ($hmos as $hmo) {
                // Check if tariff already exists
                $exists = HmoTariff::where('hmo_id', $hmo->id)
                    ->where('service_id', $service->id)
                    ->whereNull('product_id')
                    ->exists();

                if (!$exists) {
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

            if (!empty($tariffs)) {
                HmoTariff::insert($tariffs);
                Log::info("Created " . count($tariffs) . " tariff entries for service: " . $service->id);
            }
        } catch (\Exception $e) {
            Log::error("Failed to create tariffs for service {$service->id}: " . $e->getMessage());
        }
    }
}
