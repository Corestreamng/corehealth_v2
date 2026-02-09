<?php

namespace App\Observers;

use App\Models\Bed;
use App\Models\service;
use App\Models\ServicePrice;
use Illuminate\Support\Facades\Log;

/**
 * BedObserver
 *
 * Ensures every bed has a properly configured service in the bed category.
 * This provides data integrity for bed billing.
 *
 * Auto-creates bed service if:
 * - Bed has no service_id
 * - Bed's service is in wrong category
 *
 * Auto-syncs:
 * - Service name when bed name/ward changes
 * - Service price when bed price changes
 */
class BedObserver
{
    /**
     * Handle the Bed "saving" event.
     * Ensures bed has proper service before save.
     */
    public function saving(Bed $bed): void
    {
        $bedServiceCategoryId = appsettings('bed_service_category_id');

        if (!$bedServiceCategoryId) {
            Log::warning('BedObserver: bed_service_category_id not configured');
            return;
        }

        // Check if bed has a valid service
        $needsService = false;

        if (!$bed->service_id) {
            $needsService = true;
        } else {
            $existingService = service::find($bed->service_id);
            if (!$existingService || $existingService->category_id != $bedServiceCategoryId) {
                $needsService = true;
            }
        }

        if ($needsService) {
            // Create or find bed service
            $serviceName = $this->generateServiceName($bed);
            $serviceCode = $this->generateServiceCode($bed);

            // Check if service already exists
            $bedService = service::where('service_code', $serviceCode)
                ->where('category_id', $bedServiceCategoryId)
                ->first();

            if (!$bedService) {
                $bedService = service::create([
                    'user_id' => auth()->id() ?? 1,
                    'category_id' => $bedServiceCategoryId,
                    'service_name' => $serviceName,
                    'service_code' => $serviceCode,
                    'status' => 1,
                    'price_assign' => 1,
                ]);

                Log::info("BedObserver: Created service {$bedService->id} for bed {$bed->id}");
            }

            $bed->service_id = $bedService->id;
        }
    }

    /**
     * Handle the Bed "saved" event.
     * Syncs service price after bed is saved.
     */
    public function saved(Bed $bed): void
    {
        if (!$bed->service_id) {
            return;
        }

        // Sync service price with bed price
        $servicePrice = ServicePrice::firstOrNew(['service_id' => $bed->service_id]);
        $servicePrice->cost_price = $bed->price ?? 0;
        $servicePrice->sale_price = $bed->price ?? 0;
        $servicePrice->status = 1;
        $servicePrice->save();

        // Sync service name if bed name/ward changed
        $service = service::find($bed->service_id);
        if ($service) {
            $newName = $this->generateServiceName($bed);
            $newCode = $this->generateServiceCode($bed);

            if ($service->service_name !== $newName || $service->service_code !== $newCode) {
                $service->service_name = $newName;
                $service->service_code = $newCode;
                $service->save();
            }
        }
    }

    /**
     * Generate service name from bed details.
     */
    protected function generateServiceName(Bed $bed): string
    {
        $parts = ['Bed', $bed->name, $bed->ward];
        if ($bed->unit) {
            $parts[] = $bed->unit;
        }
        return implode(' ', array_filter($parts));
    }

    /**
     * Generate service code from bed details.
     */
    protected function generateServiceCode(Bed $bed): string
    {
        return strtoupper(str_replace(' ', '-', $this->generateServiceName($bed)));
    }
}
