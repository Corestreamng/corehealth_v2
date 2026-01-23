<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Store;
use App\Models\StockBatch;
use App\Models\StockBatchTransaction;
use App\Models\StoreStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Service: StockService
 *
 * Plan Reference: Phase 3 - Services
 * Purpose: Centralized stock management operations using batch-based inventory
 *
 * Key Features:
 * - FIFO dispensing from batches
 * - Batch creation and management
 * - Stock level calculations
 * - Store stock synchronization
 *
 * Related Models: StockBatch, StockBatchTransaction, StoreStock, Product, Store
 * Related Files:
 * - app/Http/Controllers/PharmacyWorkbenchController.php
 * - app/Http/Controllers/StoreWorkbenchController.php
 */
class StockService
{
    /**
     * Get available stock for a product in a store
     *
     * @param int $productId
     * @param int $storeId
     * @return int
     */
    public function getAvailableStock(int $productId, int $storeId): int
    {
        return StockBatch::where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->sum('current_qty');
    }

    /**
     * Get available batches for a product in a store (FIFO order)
     *
     * @param int $productId
     * @param int $storeId
     * @return Collection
     */
    public function getAvailableBatches(int $productId, int $storeId): Collection
    {
        return StockBatch::where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->hasStock()
            ->fifoOrder()
            ->get();
    }

    /**
     * Dispense stock using FIFO method
     *
     * @param int $productId
     * @param int $storeId
     * @param int $qty Quantity to dispense
     * @param string|null $referenceType Polymorphic reference type
     * @param int|null $referenceId Polymorphic reference ID
     * @param string|null $notes
     * @return array Array of [batch_id => qty_deducted]
     * @throws \Exception
     */
    public function dispenseStock(
        int $productId,
        int $storeId,
        int $qty,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): array {
        $availableStock = $this->getAvailableStock($productId, $storeId);

        if ($availableStock < $qty) {
            throw new \Exception("Insufficient stock. Available: {$availableStock}, Required: {$qty}");
        }

        $batches = $this->getAvailableBatches($productId, $storeId);
        $dispensed = [];
        $remainingQty = $qty;

        return DB::transaction(function () use ($batches, $remainingQty, $referenceType, $referenceId, $notes, &$dispensed) {
            foreach ($batches as $batch) {
                if ($remainingQty <= 0) break;

                $deductQty = min($batch->current_qty, $remainingQty);

                $batch->deductStock(
                    $deductQty,
                    StockBatchTransaction::TYPE_OUT,
                    $referenceType,
                    $referenceId,
                    $notes
                );

                $dispensed[$batch->id] = $deductQty;
                $remainingQty -= $deductQty;
            }

            // Update store_stocks aggregate
            $this->syncStoreStock($batches->first()->product_id, $batches->first()->store_id);

            return $dispensed;
        });
    }

    /**
     * Dispense from a specific batch
     *
     * @param int $batchId
     * @param int $qty
     * @param string|null $referenceType
     * @param int|null $referenceId
     * @param string|null $notes
     * @return StockBatchTransaction
     */
    public function dispenseFromBatch(
        int $batchId,
        int $qty,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): StockBatchTransaction {
        $batch = StockBatch::findOrFail($batchId);

        if ($batch->current_qty < $qty) {
            throw new \Exception("Insufficient stock in batch. Available: {$batch->current_qty}, Required: {$qty}");
        }

        return DB::transaction(function () use ($batch, $qty, $referenceType, $referenceId, $notes) {
            $transaction = $batch->deductStock(
                $qty,
                StockBatchTransaction::TYPE_OUT,
                $referenceType,
                $referenceId,
                $notes
            );

            $this->syncStoreStock($batch->product_id, $batch->store_id);

            return $transaction;
        });
    }

    /**
     * Create a new stock batch
     *
     * @param array $data Batch data
     * @return StockBatch
     */
    public function createBatch(array $data): StockBatch
    {
        return DB::transaction(function () use ($data) {
            // Generate batch_number if not provided
            $batchNumber = $data['batch_number'] ?? 'BATCH-' . now()->format('YmdHis') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $batchName = $data['batch_name'] ?? $batchNumber . '-' . now()->format('YmdHis');

            $batch = StockBatch::create([
                'product_id' => $data['product_id'],
                'store_id' => $data['store_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'batch_name' => $batchName,
                'batch_number' => $batchNumber,
                'initial_qty' => $data['qty'],
                'current_qty' => $data['qty'],
                'sold_qty' => 0,
                'cost_price' => $data['cost_price'] ?? 0,
                'expiry_date' => $data['expiry_date'] ?? null,
                'received_date' => $data['received_date'] ?? now(),
                'source' => $data['source'] ?? StockBatch::SOURCE_MANUAL,
                'purchase_order_item_id' => $data['purchase_order_item_id'] ?? null,
                'source_requisition_id' => $data['source_requisition_id'] ?? null,
                'created_by' => $data['created_by'] ?? auth()->id(),
                'is_active' => true,
            ]);

            // Record the initial stock transaction
            StockBatchTransaction::create([
                'stock_batch_id' => $batch->id,
                'type' => StockBatchTransaction::TYPE_IN,
                'qty' => $data['qty'],
                'balance_after' => $data['qty'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? 'Initial batch creation',
                'performed_by' => auth()->id(),
            ]);

            // Sync store_stocks
            $this->syncStoreStock($batch->product_id, $batch->store_id);

            return $batch;
        });
    }

    /**
     * Transfer stock between stores
     *
     * @param int $productId
     * @param int $fromStoreId
     * @param int $toStoreId
     * @param int $qty
     * @param int|null $sourceBatchId Specific batch to transfer from (optional, uses FIFO if null)
     * @param array $options Additional options (expiry_date, cost_price, requisition_id, notes)
     * @return StockBatch The new batch created in destination store
     */
    public function transferStock(
        int $productId,
        int $fromStoreId,
        int $toStoreId,
        int $qty,
        ?int $sourceBatchId = null,
        array $options = []
    ): StockBatch {
        return DB::transaction(function () use ($productId, $fromStoreId, $toStoreId, $qty, $sourceBatchId, $options) {
            $costPrice = 0;
            $expiryDate = $options['expiry_date'] ?? null;

            if ($sourceBatchId) {
                // Transfer from specific batch
                $sourceBatch = StockBatch::findOrFail($sourceBatchId);

                if ($sourceBatch->current_qty < $qty) {
                    throw new \Exception("Insufficient stock in source batch");
                }

                $sourceBatch->deductStock(
                    $qty,
                    StockBatchTransaction::TYPE_TRANSFER_OUT,
                    $options['reference_type'] ?? null,
                    $options['reference_id'] ?? null,
                    $options['notes'] ?? "Transferred to store ID: {$toStoreId}"
                );

                // Use source batch cost_price, fallback to product's buy price if not set
                $costPrice = $sourceBatch->cost_price;
                if (empty($costPrice) || $costPrice <= 0) {
                    $productPrice = \App\Models\Price::where('product_id', $productId)->first();
                    $costPrice = $productPrice->pr_buy_price ?? 0;
                }
                $expiryDate = $expiryDate ?? $sourceBatch->expiry_date;
            } else {
                // FIFO transfer
                $batches = $this->getAvailableBatches($productId, $fromStoreId);
                $remainingQty = $qty;
                $totalCost = 0;
                $totalQtyProcessed = 0;

                foreach ($batches as $batch) {
                    if ($remainingQty <= 0) break;

                    $deductQty = min($batch->current_qty, $remainingQty);

                    $batch->deductStock(
                        $deductQty,
                        StockBatchTransaction::TYPE_TRANSFER_OUT,
                        $options['reference_type'] ?? null,
                        $options['reference_id'] ?? null,
                        $options['notes'] ?? "Transferred to store ID: {$toStoreId}"
                    );

                    // Accumulate total cost for weighted average (use batch cost or fallback to product price)
                    $batchCost = $batch->cost_price;
                    if (empty($batchCost) || $batchCost <= 0) {
                        $productPrice = \App\Models\Price::where('product_id', $productId)->first();
                        $batchCost = $productPrice->pr_buy_price ?? 0;
                    }
                    $totalCost += ($batchCost * $deductQty);
                    $totalQtyProcessed += $deductQty;
                    $remainingQty -= $deductQty;

                    // Use earliest expiry date if not set
                    if (!$expiryDate && $batch->expiry_date) {
                        $expiryDate = $batch->expiry_date;
                    }
                }

                // Calculate weighted average cost price
                $costPrice = $totalQtyProcessed > 0 ? ($totalCost / $totalQtyProcessed) : 0;
            }

            // Create new batch in destination store
            $batchNumber = 'TRF-' . now()->format('YmdHis') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $newBatch = $this->createBatch([
                'product_id' => $productId,
                'store_id' => $toStoreId,
                'batch_number' => $options['batch_number'] ?? $batchNumber,
                'qty' => $qty,
                'cost_price' => $options['cost_price'] ?? $costPrice,
                'expiry_date' => $expiryDate,
                'source' => StockBatch::SOURCE_TRANSFER_IN,
                'source_requisition_id' => $options['requisition_id'] ?? null,
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
                'notes' => $options['notes'] ?? "Transferred from store ID: {$fromStoreId}",
            ]);

            // Sync both stores
            $this->syncStoreStock($productId, $fromStoreId);
            $this->syncStoreStock($productId, $toStoreId);

            return $newBatch;
        });
    }

    /**
     * Adjust stock (positive or negative)
     *
     * @param int $batchId
     * @param int $adjustmentQty (positive to add, negative to subtract)
     * @param string $reason
     * @return StockBatchTransaction
     */
    public function adjustStock(int $batchId, int $adjustmentQty, string $reason): StockBatchTransaction
    {
        $batch = StockBatch::findOrFail($batchId);

        if ($adjustmentQty < 0 && abs($adjustmentQty) > $batch->current_qty) {
            throw new \Exception("Cannot reduce stock below zero");
        }

        return DB::transaction(function () use ($batch, $adjustmentQty, $reason) {
            if ($adjustmentQty > 0) {
                $transaction = $batch->addStock(
                    $adjustmentQty,
                    StockBatchTransaction::TYPE_ADJUSTMENT,
                    null,
                    null,
                    "Positive adjustment: {$reason}"
                );
            } else {
                $transaction = $batch->deductStock(
                    abs($adjustmentQty),
                    StockBatchTransaction::TYPE_ADJUSTMENT,
                    null,
                    null,
                    "Negative adjustment: {$reason}"
                );
            }

            $this->syncStoreStock($batch->product_id, $batch->store_id);

            return $transaction;
        });
    }

    /**
     * Write off expired stock
     *
     * @param int $batchId
     * @param int|null $qty Quantity to write off (entire batch if null)
     * @param string|null $notes
     * @return StockBatchTransaction
     */
    public function writeOffExpired(int $batchId, ?int $qty = null, ?string $notes = null): StockBatchTransaction
    {
        $batch = StockBatch::findOrFail($batchId);
        $writeOffQty = $qty ?? $batch->current_qty;

        return DB::transaction(function () use ($batch, $writeOffQty, $notes) {
            $transaction = $batch->deductStock(
                $writeOffQty,
                StockBatchTransaction::TYPE_EXPIRED,
                null,
                null,
                $notes ?? "Expired stock write-off"
            );

            // Deactivate batch if fully written off
            if ($batch->current_qty <= 0) {
                $batch->is_active = false;
                $batch->save();
            }

            $this->syncStoreStock($batch->product_id, $batch->store_id);

            return $transaction;
        });
    }

    /**
     * Write off damaged stock
     *
     * @param int $batchId
     * @param int $qty
     * @param string $reason
     * @return StockBatchTransaction
     */
    public function writeOffDamaged(int $batchId, int $qty, string $reason): StockBatchTransaction
    {
        $batch = StockBatch::findOrFail($batchId);

        return DB::transaction(function () use ($batch, $qty, $reason) {
            $transaction = $batch->deductStock(
                $qty,
                StockBatchTransaction::TYPE_DAMAGED,
                null,
                null,
                "Damaged: {$reason}"
            );

            $this->syncStoreStock($batch->product_id, $batch->store_id);

            return $transaction;
        });
    }

    /**
     * Sync store_stocks aggregate table with batch totals
     *
     * @param int $productId
     * @param int $storeId
     * @return StoreStock
     */
    public function syncStoreStock(int $productId, int $storeId): StoreStock
    {
        $totalQty = StockBatch::where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->sum('current_qty');

        return StoreStock::updateOrCreate(
            [
                'product_id' => $productId,
                'store_id' => $storeId,
            ],
            [
                'qty' => $totalQty,
                'last_restocked_at' => $totalQty > 0 ? now() : null,
            ]
        );
    }

    /**
     * Get low stock products for a store
     *
     * @param int|null $storeId
     * @return Collection
     */
    public function getLowStockProducts(?int $storeId): Collection
    {
        $query = StoreStock::where('is_active', true)
            ->whereRaw('current_quantity <= reorder_level')
            ->with('product');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }

    /**
     * Get expiring batches for a store
     *
     * @param int|null $storeId
     * @param int $days Days until expiry
     * @return Collection
     */
    public function getExpiringBatches(?int $storeId, int $days = 30): Collection
    {
        $query = StockBatch::active()
            ->hasStock()
            ->expiringSoon($days)
            ->with('product')
            ->orderBy('expiry_date', 'asc');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }

    /**
     * Get expired batches for a store
     *
     * @param int $storeId
     * @return Collection
     */
    public function getExpiredBatches(int $storeId): Collection
    {
        return StockBatch::where('store_id', $storeId)
            ->active()
            ->hasStock()
            ->expired()
            ->with('product')
            ->get();
    }

    /**
     * Get stock value report for a store
     *
     * @param int|null $storeId Specific store ID or null for all stores
     * @return array
     */
    public function getStockValueReport(?int $storeId = null): array
    {
        $query = StockBatch::query()
            ->active()
            ->hasStock()
            ->with(['product', 'store']);

        // Filter by store if specified
        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        $batches = $query->get();

        $totalValue = 0;
        $productValues = [];
        $storeValues = [];

        foreach ($batches as $batch) {
            $value = $batch->current_qty * $batch->cost_price;
            $totalValue += $value;

            // Group by product
            $productId = $batch->product_id;
            if (!isset($productValues[$productId])) {
                $productValues[$productId] = [
                    'product' => $batch->product,
                    'total_qty' => 0,
                    'total_value' => 0,
                    'stores' => [],
                ];
            }
            $productValues[$productId]['total_qty'] += $batch->current_qty;
            $productValues[$productId]['total_value'] += $value;

            // Track store breakdown for this product (useful for all-stores view)
            $storeIdKey = $batch->store_id;
            if (!isset($productValues[$productId]['stores'][$storeIdKey])) {
                $productValues[$productId]['stores'][$storeIdKey] = [
                    'store' => $batch->store,
                    'qty' => 0,
                    'value' => 0,
                ];
            }
            $productValues[$productId]['stores'][$storeIdKey]['qty'] += $batch->current_qty;
            $productValues[$productId]['stores'][$storeIdKey]['value'] += $value;

            // Group by store (for all-stores summary)
            if (!isset($storeValues[$storeIdKey])) {
                $storeValues[$storeIdKey] = [
                    'store' => $batch->store,
                    'total_qty' => 0,
                    'total_value' => 0,
                    'product_count' => 0,
                ];
            }
            $storeValues[$storeIdKey]['total_qty'] += $batch->current_qty;
            $storeValues[$storeIdKey]['total_value'] += $value;
        }

        // Count unique products per store
        foreach ($productValues as $product) {
            foreach ($product['stores'] as $storeIdKey => $storeData) {
                $storeValues[$storeIdKey]['product_count']++;
            }
        }

        return [
            'total_value' => $totalValue,
            'products' => array_values($productValues),
            'stores' => array_values($storeValues),
        ];
    }
}
