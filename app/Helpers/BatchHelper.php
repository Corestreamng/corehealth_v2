<?php

namespace App\Helpers;

use App\Models\StockBatch;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Helper: BatchHelper
 *
 * Plan Reference: Phase 3 - Helpers
 * Purpose: Utility functions for batch-based stock operations
 *
 * Key Features:
 * - FIFO batch selection
 * - Batch availability checks
 * - Expiry management
 * - Display formatting
 *
 * Related Models: StockBatch, Product, Store
 * Related Files:
 * - app/Services/StockService.php
 * - resources/views/pharmacy_workbench/dispense_modal.blade.php
 */
class BatchHelper
{
    /**
     * Get best batches for dispensing using FIFO
     * Prioritizes by: 1) Expiring soonest, 2) Oldest received date
     *
     * @param int $productId
     * @param int $storeId
     * @param int $requiredQty
     * @return Collection Batches that can fulfill the quantity
     */
    public static function getBestBatchesForDispensing(int $productId, int $storeId, int $requiredQty): Collection
    {
        $batches = StockBatch::where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->hasStock()
            // Prioritize expiring batches first, then FIFO
            ->orderByRaw('CASE WHEN expiry_date IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('expiry_date', 'asc')
            ->orderBy('received_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $selectedBatches = collect();
        $remainingQty = $requiredQty;

        foreach ($batches as $batch) {
            if ($remainingQty <= 0) break;

            $useQty = min($batch->current_qty, $remainingQty);
            $selectedBatches->push([
                'batch' => $batch,
                'use_qty' => $useQty,
            ]);
            $remainingQty -= $useQty;
        }

        return $selectedBatches;
    }

    /**
     * Check if quantity can be fulfilled from a store
     *
     * @param int $productId
     * @param int $storeId
     * @param int $requiredQty
     * @return bool
     */
    public static function canFulfillQuantity(int $productId, int $storeId, int $requiredQty): bool
    {
        $available = StockBatch::where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->sum('current_qty');

        return $available >= $requiredQty;
    }

    /**
     * Get total available quantity for a product in a store
     *
     * @param int $productId
     * @param int $storeId
     * @return int
     */
    public static function getAvailableQuantity(int $productId, int $storeId): int
    {
        return StockBatch::where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->sum('current_qty');
    }

    /**
     * Get batches with expiry warnings
     *
     * @param int|null $storeId Filter by store (null for all stores)
     * @param int $warningDays Days before expiry to show warning
     * @param int $criticalDays Days before expiry to show critical warning
     * @return Collection
     */
    public static function getBatchesWithExpiryWarning(?int $storeId = null, int $warningDays = 90, int $criticalDays = 30): Collection
    {
        $query = StockBatch::active()
            ->hasStock()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($warningDays))
            ->with('product', 'store');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->orderBy('expiry_date', 'asc')
            ->get()
            ->map(function ($batch) use ($criticalDays) {
                $daysToExpiry = now()->diffInDays($batch->expiry_date, false);

                return [
                    'batch' => $batch,
                    'days_to_expiry' => $daysToExpiry,
                    'is_expired' => $daysToExpiry < 0,
                    'is_critical' => $daysToExpiry >= 0 && $daysToExpiry <= $criticalDays,
                    'warning_level' => $daysToExpiry < 0 ? 'expired' : ($daysToExpiry <= $criticalDays ? 'critical' : 'warning'),
                ];
            });
    }

    /**
     * Format batch for display in dropdown/select
     *
     * @param StockBatch $batch
     * @return string
     */
    public static function formatBatchForSelect(StockBatch $batch): string
    {
        $label = $batch->batch_number ?? "Batch #{$batch->id}";
        $qty = $batch->current_qty;
        $expiry = $batch->expiry_date ? " | Exp: {$batch->expiry_date->format('d M Y')}" : '';
        $cost = $batch->cost_price ? " | ₦" . number_format($batch->cost_price, 2) : '';

        return "{$label} ({$qty} units){$expiry}{$cost}";
    }

    /**
     * Get batch selection options for a product in a store
     * Formatted for use in HTML select/dropdown
     *
     * @param int $productId
     * @param int $storeId
     * @return array
     */
    public static function getBatchSelectOptions(int $productId, int $storeId): array
    {
        $batches = StockBatch::where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->hasStock()
            ->fifoOrder()
            ->get();

        return $batches->map(function ($batch) {
            $isExpiringSoon = $batch->expiry_date && $batch->expiry_date->diffInDays(now()) <= 30;
            $isExpired = $batch->is_expired;

            return [
                'id' => $batch->id,
                'label' => self::formatBatchForSelect($batch),
                'qty' => $batch->current_qty,
                'cost_price' => $batch->cost_price,
                'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                'is_expiring_soon' => $isExpiringSoon,
                'is_expired' => $isExpired,
                'batch_number' => $batch->batch_number,
            ];
        })->toArray();
    }

    /**
     * Calculate weighted average cost for batches
     *
     * @param Collection $batches
     * @return float
     */
    public static function calculateWeightedAverageCost(Collection $batches): float
    {
        $totalValue = 0;
        $totalQty = 0;

        foreach ($batches as $batch) {
            $totalValue += $batch->current_qty * $batch->cost_price;
            $totalQty += $batch->current_qty;
        }

        return $totalQty > 0 ? $totalValue / $totalQty : 0;
    }

    /**
     * Get stock summary for a product across all stores
     *
     * @param int $productId
     * @return array
     */
    public static function getProductStockSummary(int $productId): array
    {
        $batches = StockBatch::where('product_id', $productId)
            ->active()
            ->with('store')
            ->get();

        $summary = [
            'total_qty' => 0,
            'total_value' => 0,
            'by_store' => [],
            'expiring_soon' => 0,
            'expired' => 0,
        ];

        foreach ($batches as $batch) {
            $storeName = $batch->store->store_name ?? 'Unknown';

            if (!isset($summary['by_store'][$storeName])) {
                $summary['by_store'][$storeName] = ['qty' => 0, 'value' => 0];
            }

            $summary['by_store'][$storeName]['qty'] += $batch->current_qty;
            $summary['by_store'][$storeName]['value'] += $batch->current_qty * $batch->cost_price;

            $summary['total_qty'] += $batch->current_qty;
            $summary['total_value'] += $batch->current_qty * $batch->cost_price;

            if ($batch->is_expired) {
                $summary['expired'] += $batch->current_qty;
            } elseif ($batch->expiry_date && $batch->days_until_expiry <= 30) {
                $summary['expiring_soon'] += $batch->current_qty;
            }
        }

        return $summary;
    }

    /**
     * Suggest optimal fulfillment strategy for a quantity
     * Returns which batches to use and in what quantities
     *
     * @param int $productId
     * @param int $storeId
     * @param int $requiredQty
     * @return array
     */
    public static function suggestFulfillmentStrategy(int $productId, int $storeId, int $requiredQty): array
    {
        $batches = self::getBestBatchesForDispensing($productId, $storeId, $requiredQty);

        $totalAvailable = $batches->sum('use_qty');
        $canFulfill = $totalAvailable >= $requiredQty;

        return [
            'can_fulfill' => $canFulfill,
            'required_qty' => $requiredQty,
            'available_qty' => $totalAvailable,
            'shortage' => $canFulfill ? 0 : $requiredQty - $totalAvailable,
            'batches' => $batches->map(fn($b) => [
                'batch_id' => $b['batch']->id,
                'batch_number' => $b['batch']->batch_number,
                'use_qty' => $b['use_qty'],
                'remaining_after' => $b['batch']->current_qty - $b['use_qty'],
                'cost_price' => $b['batch']->cost_price,
                'expiry_date' => $b['batch']->expiry_date?->format('Y-m-d'),
            ])->toArray(),
        ];
    }
}
