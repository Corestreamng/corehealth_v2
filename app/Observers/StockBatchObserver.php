<?php

namespace App\Observers;

use App\Models\StockBatch;
use App\Services\StockService;
use Illuminate\Support\Facades\Log;

/**
 * Observer: StockBatchObserver
 *
 * Purpose: Automatically keeps store_stocks.current_quantity (and legacy stocks table)
 *          in sync with the sum of stock_batches.current_qty whenever a batch is
 *          created, updated, or deleted.
 *
 * This makes store_stocks a true "computed cache" that always reflects batch totals.
 *
 * Related:
 *  - app/Services/StockService.php::syncStoreStock()
 *  - app/Models/StockBatch.php
 *  - app/Models/StoreStock.php
 */
class StockBatchObserver
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * After a new batch is created, sync the aggregate store_stocks row.
     */
    public function created(StockBatch $batch): void
    {
        $this->syncIfNeeded($batch, 'created');
    }

    /**
     * After a batch is updated (qty changed, deactivated, etc.), re-sync.
     */
    public function updated(StockBatch $batch): void
    {
        // Only sync when stock-relevant columns change (includes cost_price for price sync)
        $relevantColumns = ['current_qty', 'is_active', 'store_id', 'product_id', 'cost_price'];

        if ($batch->wasChanged($relevantColumns)) {
            $this->syncIfNeeded($batch, 'updated');

            // If product_id or store_id changed, also sync the OLD product+store combo
            if ($batch->wasChanged('product_id') || $batch->wasChanged('store_id')) {
                $oldProductId = $batch->getOriginal('product_id');
                $oldStoreId = $batch->getOriginal('store_id');
                if ($oldProductId && $oldStoreId) {
                    $this->safeSync($oldProductId, $oldStoreId, 'updated-old-combo');
                }
            }
        }
    }

    /**
     * After a batch is soft-deleted, re-sync (current_qty effectively removed from active sum).
     */
    public function deleted(StockBatch $batch): void
    {
        $this->syncIfNeeded($batch, 'deleted');
    }

    /**
     * After a batch is restored from soft-delete, re-sync.
     */
    public function restored(StockBatch $batch): void
    {
        $this->syncIfNeeded($batch, 'restored');
    }

    /**
     * Trigger sync only if product_id and store_id are present.
     */
    private function syncIfNeeded(StockBatch $batch, string $event): void
    {
        if ($batch->product_id && $batch->store_id) {
            $this->safeSync($batch->product_id, $batch->store_id, $event);
        }
    }

    /**
     * Run sync wrapped in try/catch so a sync failure never blocks the primary operation.
     */
    private function safeSync(int $productId, int $storeId, string $event): void
    {
        try {
            $this->stockService->syncStoreStock($productId, $storeId);
        } catch (\Throwable $e) {
            Log::error("StockBatchObserver sync failed on {$event}: " . $e->getMessage(), [
                'product_id' => $productId,
                'store_id' => $storeId,
            ]);
        }
    }
}
