<?php

namespace App\Services;

use App\Models\StoreRequisition;
use App\Models\StoreRequisitionItem;
use App\Models\StoreRequisitionReturn;
use App\Models\StockBatch;
use App\Models\StockBatchTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Service: RequisitionService
 *
 * Plan Reference: Phase 3 - Services
 * Purpose: Handle inter-store stock requisition workflow
 *
 * Workflow:
 * 1. Create requisition (request items from another store)
 * 2. Admin/Manager approves or rejects
 * 3. Source store fulfills (transfers stock)
 * 4. Destination receives (new batch created)
 *
 * Related Models: StoreRequisition, StoreRequisitionItem, StockBatch
 * Related Files:
 * - app/Http/Controllers/StoreRequisitionController.php
 * - app/Services/StockService.php
 */
class RequisitionService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Create a new requisition
     *
     * @param array $data Requisition header data
     * @param array $items Array of items [['product_id', 'requested_qty'], ...]
     * @return StoreRequisition
     */
    public function create(array $data, array $items): StoreRequisition
    {
        return DB::transaction(function () use ($data, $items) {
            $requisition = StoreRequisition::create([
                'from_store_id' => $data['from_store_id'],
                'to_store_id' => $data['to_store_id'],
                'request_notes' => $data['request_notes'] ?? null,
                'status' => StoreRequisition::STATUS_PENDING,
            ]);

            foreach ($items as $itemData) {
                StoreRequisitionItem::create([
                    'store_requisition_id' => $requisition->id,
                    'product_id' => $itemData['product_id'],
                    'requested_qty' => $itemData['requested_qty'],
                    'packaging_id' => $itemData['packaging_id'] ?? null,
                    'packaging_qty' => $itemData['packaging_qty'] ?? null,
                    'status' => StoreRequisitionItem::STATUS_PENDING,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            return $requisition->fresh(['items', 'items.product', 'fromStore', 'toStore', 'requester']);
        });
    }

    /**
     * Add item to an existing requisition
     *
     * @param StoreRequisition $requisition
     * @param array $itemData
     * @return StoreRequisitionItem
     */
    public function addItem(StoreRequisition $requisition, array $itemData): StoreRequisitionItem
    {
        if ($requisition->status !== StoreRequisition::STATUS_PENDING) {
            throw new \Exception("Cannot add items to a requisition that is not pending");
        }

        return StoreRequisitionItem::create([
            'store_requisition_id' => $requisition->id,
            'product_id' => $itemData['product_id'],
            'requested_qty' => $itemData['requested_qty'],
            'status' => StoreRequisitionItem::STATUS_PENDING,
            'notes' => $itemData['notes'] ?? null,
        ]);
    }

    /**
     * Remove item from requisition
     *
     * @param StoreRequisitionItem $item
     * @return bool
     */
    public function removeItem(StoreRequisitionItem $item): bool
    {
        $requisition = $item->requisition;

        if ($requisition->status !== StoreRequisition::STATUS_PENDING) {
            throw new \Exception("Cannot remove items from a requisition that is not pending");
        }

        return $item->delete();
    }

    /**
     * Update an editable requisition (PENDING / APPROVED / PARTIAL).
     *
     * items[] format:
     *   { id: null,  product_id, requested_qty, packaging_id }   → add (PENDING only)
     *   { id: 123,   _delete: true }                              → remove (PENDING only)
     *   { id: 123,   requested_qty: 50 }                          → update qty
     */
    public function updateRequisition(StoreRequisition $requisition, array $data): StoreRequisition
    {
        if (! $requisition->canEditHeader()) {
            throw new \Exception("Requisition cannot be edited in status: {$requisition->status}");
        }

        return DB::transaction(function () use ($requisition, $data) {
            // 1. Update header details
            if (array_key_exists('request_notes', $data)) {
                $requisition->request_notes = $data['request_notes'];
            }
            if ($requisition->status === StoreRequisition::STATUS_PENDING) {
                if (!empty($data['to_store_id'])) $requisition->to_store_id = $data['to_store_id'];
                if (!empty($data['from_store_id'])) $requisition->from_store_id = $data['from_store_id'];
            }

            // Stamp edit tracking
            $requisition->edited_by  = auth()->id();
            $requisition->edited_at  = now();
            $requisition->edit_count = ($requisition->edit_count ?? 0) + 1;
            $requisition->save();

            // 2. Process item changes
            foreach ($data['items'] ?? [] as $itemData) {
                // ── ADD new item (PENDING only) ──────────────────────────────
                if (empty($itemData['item_id']) && $requisition->canEditItems()) {
                    StoreRequisitionItem::create([
                        'store_requisition_id' => $requisition->id,
                        'product_id'           => $itemData['product_id'],
                        'requested_qty'        => (int) $itemData['qty'],
                        'packaging_id'         => $itemData['packaging_id'] ?? null,
                        'packaging_qty'        => $itemData['packaging_qty'] ?? null,
                        'status'               => StoreRequisitionItem::STATUS_PENDING,
                        'notes'                => $itemData['notes'] ?? null,
                    ]);
                    continue;
                }

                $item = StoreRequisitionItem::find($itemData['item_id'] ?? null);
                if (! $item || $item->store_requisition_id !== $requisition->id) {
                    continue;
                }

                // ── DELETE item (PENDING only) ───────────────────────────────
                if (! empty($itemData['_delete'])) {
                    if ($requisition->canEditItems()) {
                        $item->delete();
                    }
                    continue;
                }

                // ── UPDATE qty ───────────────────────────────────────────────
                if (isset($itemData['qty']) && $requisition->canEditItemQty($item)) {
                    $floor  = $item->fulfilled_qty ?? 0;
                    $newQty = max((int) $itemData['qty'], $floor + 1);

                    $item->requested_qty = $newQty;
                    $item->packaging_id  = $itemData['packaging_id'] ?? $item->packaging_id;
                    if (array_key_exists('packaging_qty', $itemData)) {
                        $item->packaging_qty = $itemData['packaging_qty'];
                    }

                    // For APPROVED/PARTIAL carry approval forward to new qty (no re-approval needed)
                    if (in_array($requisition->status, [
                        StoreRequisition::STATUS_APPROVED,
                        StoreRequisition::STATUS_PARTIAL,
                    ])) {
                        $item->approved_qty = $newQty;
                    }

                    // Update notes if provided
                    if (isset($itemData['notes'])) {
                        $item->notes = $itemData['notes'];
                    }

                    $item->save();
                }
            }

            return $requisition->fresh(['items', 'items.product', 'items.packaging', 'fromStore', 'toStore', 'editor']);
        });
    }

    /**
     * Approve requisition
     *
     * @param StoreRequisition $requisition
     * @param array|null $approvedQtys Array of ['item_id' => 'approved_qty'] (optional, defaults to requested_qty)
     * @param string|null $notes Approval notes
     * @return StoreRequisition
     */
    public function approve(StoreRequisition $requisition, ?array $approvedQtys = null, ?string $notes = null): StoreRequisition
    {
        if (!$requisition->canApprove()) {
            throw new \Exception("Requisition cannot be approved");
        }

        // Cache the initial status to know if we are approving from pending or rejected
        $initialStatus = $requisition->status;

        return DB::transaction(function () use ($requisition, $approvedQtys, $notes, $initialStatus) {
            // Update approved quantities for each item
            foreach ($requisition->items as $item) {
                // Preserve rejection status if item was individually rejected prior to final approval,
                // unless we are globally approving a previously rejected requisition.
                if ($item->status === StoreRequisitionItem::STATUS_REJECTED && $initialStatus === StoreRequisition::STATUS_PENDING) {
                    $item->approved_qty = 0;
                    $item->save();
                    continue;
                }

                $approvedQty = $approvedQtys[$item->id] ?? $item->requested_qty;

                $item->approved_qty = $approvedQty;
                $item->status = $approvedQty > 0
                    ? StoreRequisitionItem::STATUS_APPROVED
                    : StoreRequisitionItem::STATUS_REJECTED;
                $item->save();
            }

            // Check if all items were rejected
            $approvedCount = $requisition->items()->where('status', StoreRequisitionItem::STATUS_APPROVED)->count();

            if ($approvedCount === 0) {
                $requisition->reject('All items rejected during approval');
            } else {
                $requisition->approve($notes);
            }

            return $requisition->fresh();
        });
    }

    /**
     * Reject requisition
     *
     * @param StoreRequisition $requisition
     * @param string $reason
     * @return StoreRequisition
     */
    public function reject(StoreRequisition $requisition, string $reason): StoreRequisition
    {
        if (!$requisition->canReject()) {
            throw new \Exception("Requisition cannot be rejected");
        }

        $requisition->reject($reason);
        return $requisition;
    }

    /**
     * Cancel requisition
     *
     * @param StoreRequisition $requisition
     * @return StoreRequisition
     */
    public function cancel(StoreRequisition $requisition): StoreRequisition
    {
        if (!$requisition->canCancel()) {
            throw new \Exception("Requisition cannot be cancelled");
        }

        $requisition->status = StoreRequisition::STATUS_CANCELLED;
        $requisition->save();

        $requisition->items()->update(['status' => StoreRequisitionItem::STATUS_CANCELLED]);

        return $requisition;
    }

    /**
     * Reverse an item's approval/rejection back to pending
     *
     * @param StoreRequisitionItem $item
     * @return StoreRequisitionItem
     */
    public function reverseItem(StoreRequisitionItem $item): StoreRequisitionItem
    {
        if (!$item->canReverse()) {
            throw new \Exception("Item cannot be reversed");
        }

        return DB::transaction(function () use ($item) {
            $item->status = StoreRequisitionItem::STATUS_PENDING;
            $item->approved_qty = null;
            $item->save();

            $requisition = $item->requisition;
            if (in_array($requisition->status, [StoreRequisition::STATUS_APPROVED, StoreRequisition::STATUS_REJECTED])) {
                $requisition->status = StoreRequisition::STATUS_PENDING;
                $requisition->save();
            }

            return $item->fresh();
        });
    }

    /**
     * Fulfill requisition items with multi-batch support
     *
     * @param StoreRequisition $requisition
     * @param array $fulfillments Array of [item_id => ['batches' => [batch_id => qty], 'total_qty' => X]]
     * @return array Created batches at destination
     */
    public function fulfill(StoreRequisition $requisition, array $fulfillments): array
    {
        if (!$requisition->canFulfill()) {
            throw new \Exception("Requisition cannot be fulfilled");
        }

        return DB::transaction(function () use ($requisition, $fulfillments) {
            $createdBatches = [];
            $allFullyFulfilled = true;

            foreach ($fulfillments as $itemId => $fulfillData) {
                $item = StoreRequisitionItem::findOrFail($itemId);

                if ($item->store_requisition_id !== $requisition->id) {
                    throw new \Exception("Item does not belong to this requisition");
                }

                if (!in_array($item->status, [StoreRequisitionItem::STATUS_APPROVED, StoreRequisitionItem::STATUS_PARTIAL])) {
                    continue; // Skip items that aren't approved
                }

                // Check for multi-batch format vs legacy single-batch format
                if (isset($fulfillData['batches']) && is_array($fulfillData['batches'])) {
                    // Multi-batch fulfillment - process each selected batch
                    $totalFulfilledForItem = 0;
                    $sourceBatchesUsed = [];

                    foreach ($fulfillData['batches'] as $batchId => $qty) {
                        $qty = (int) $qty;
                        if ($qty <= 0) continue;

                        // Validate batch exists and has enough stock
                        $sourceBatch = StockBatch::find($batchId);
                        if (!$sourceBatch) {
                            throw new \Exception("Batch #{$batchId} not found");
                        }

                        if ($sourceBatch->product_id !== $item->product_id) {
                            throw new \Exception("Batch #{$batchId} does not match product");
                        }

                        if ($sourceBatch->store_id !== $requisition->from_store_id) {
                            throw new \Exception("Batch #{$batchId} is not in the source store");
                        }

                        if ($sourceBatch->current_qty < $qty) {
                            throw new \Exception("Batch #{$sourceBatch->batch_number} only has {$sourceBatch->current_qty} available");
                        }

                        // Transfer stock from this specific batch
                        $newBatch = $this->stockService->transferStock(
                            $item->product_id,
                            $requisition->from_store_id,
                            $requisition->to_store_id,
                            $qty,
                            $batchId, // Specific source batch
                            [
                                'requisition_id' => $requisition->id,
                                'reference_type' => StoreRequisition::class,
                                'reference_id' => $requisition->id,
                                'notes' => "Fulfilled from requisition: {$requisition->requisition_number} (Batch: {$sourceBatch->batch_number})",
                            ]
                        );

                        $createdBatches[] = $newBatch;
                        $totalFulfilledForItem += $qty;
                        $sourceBatchesUsed[] = $sourceBatch->id;
                    }

                    if ($totalFulfilledForItem > 0) {
                        // Update item with last source/destination batch info (for audit trail)
                        // The item tracks the most recent batch but the transactions have full detail
                        $lastSourceBatch = StockBatch::find(end($sourceBatchesUsed));
                        $lastDestBatch = end($createdBatches);

                        $item->fulfill($totalFulfilledForItem, $lastSourceBatch, $lastDestBatch);
                    }
                } else {
                    // Legacy single-batch format for backward compatibility
                    $qty = $fulfillData['qty'] ?? $fulfillData['total_qty'] ?? 0;
                    if ($qty <= 0) continue;

                    $sourceBatchId = $fulfillData['batch_id'] ?? null;

                    // Transfer stock
                    $newBatch = $this->stockService->transferStock(
                        $item->product_id,
                        $requisition->from_store_id,
                        $requisition->to_store_id,
                        $qty,
                        $sourceBatchId,
                        [
                            'requisition_id' => $requisition->id,
                            'reference_type' => StoreRequisition::class,
                            'reference_id' => $requisition->id,
                            'notes' => "Fulfilled from requisition: {$requisition->requisition_number}",
                        ]
                    );

                    $createdBatches[] = $newBatch;

                    // Get the source batch that was used
                    $sourceBatch = $sourceBatchId
                        ? StockBatch::find($sourceBatchId)
                        : $this->stockService->getAvailableBatches($item->product_id, $requisition->from_store_id)->first();

                    // Update item fulfillment
                    $item->fulfill($qty, $sourceBatch, $newBatch);
                }

                if (!$item->isFullyFulfilled()) {
                    $allFullyFulfilled = false;
                }
            }

            // Update requisition status
            $allFullyFulfilled = true;
            foreach ($requisition->items as $item) {
                if (!$item->isFullyFulfilled()) {
                    $allFullyFulfilled = false;
                    break;
                }
            }

            if ($allFullyFulfilled) {
                $requisition->status = StoreRequisition::STATUS_FULFILLED;
                $requisition->fulfilled_by = auth()->id();
                $requisition->fulfilled_at = now();
            } else {
                $requisition->status = StoreRequisition::STATUS_PARTIAL;
            }
            $requisition->save();

            return $createdBatches;
        });
    }

    /**
     * Return items from a fulfilled requisition
     *
     * @param StoreRequisitionReturn $return
     * @return void
     */
    public function returnItems(StoreRequisitionReturn $return): void
    {
        DB::transaction(function () use ($return) {
            $qty = $return->qty_returned;

            // 1. Deduct from source (returning) store
            if ($return->batch_id) {
                $batch = StockBatch::find($return->batch_id);
                if ($batch && $batch->current_qty >= $qty) {
                    $batch->deductStock(
                        $qty,
                        StockBatchTransaction::TYPE_REQ_RETURN,
                        StoreRequisitionReturn::class,
                        $return->id,
                        "Req Return #{$return->id} — deducted from returning store"
                    );
                }
            } else {
                // FIFO from source store
                $this->stockService->dispenseStock(
                    $return->product_id,
                    $return->source_store_id,
                    $qty,
                    StoreRequisitionReturn::class,
                    $return->id,
                    "Req Return #{$return->id} — FIFO deduction"
                );
            }

            // 2. Re-stock at destination (origin) store if restock = true
            if ($return->restock) {
                // Find the original batch at destination store to add back to
                $destBatch = StockBatch::where('product_id', $return->product_id)
                    ->where('store_id', $return->destination_store_id)
                    ->where('is_active', true)
                    ->orderBy('received_date', 'asc')
                    ->first();

                if ($destBatch) {
                    $destBatch->addStock(
                        $qty,
                        StockBatchTransaction::TYPE_REQ_RETURN,
                        StoreRequisitionReturn::class,
                        $return->id,
                        "Req Return #{$return->id} — restocked at origin store"
                    );
                } else {
                    // No existing batch — create a new one
                    $sourceBatch = $return->batch_id
                        ? StockBatch::find($return->batch_id)
                        : null;

                    $this->stockService->createBatch([
                        'product_id'    => $return->product_id,
                        'store_id'      => $return->destination_store_id,
                        'batch_number'  => ($sourceBatch->batch_number ?? 'RET') . '-R' . $return->id,
                        'qty'           => $qty,
                        'cost_price'    => $sourceBatch->cost_price ?? 0,
                        'received_date' => now()->toDateString(),
                        'source'        => StockBatch::SOURCE_MANUAL,
                        'reference_type' => StoreRequisitionReturn::class,
                        'reference_id'   => $return->id,
                        'notes'         => "Req Return #{$return->id} — new batch at origin store",
                    ]);
                }
            }

            // Sync both stores
            $this->stockService->syncStoreStock($return->product_id, $return->source_store_id);
            $this->stockService->syncStoreStock($return->product_id, $return->destination_store_id);
        });
    }

    /**
     * Get available stock in source store for a requisition
     * Helps UI show what's available to fulfill
     *
     * @param StoreRequisition $requisition
     * @return Collection
     */
    public function getAvailableStockForRequisition(StoreRequisition $requisition): Collection
    {
        $result = collect();

        foreach ($requisition->items as $item) {
            $availableBatches = $this->stockService->getAvailableBatches(
                $item->product_id,
                $requisition->from_store_id
            );

            $result->push([
                'item_id' => $item->id,
                'product' => $item->product,
                'requested_qty' => $item->requested_qty,
                'approved_qty' => $item->approved_qty,
                'fulfilled_qty' => $item->fulfilled_qty,
                'remaining_qty' => $item->remaining_qty,
                'available_stock' => $availableBatches->sum('current_qty'),
                'batches' => $availableBatches->map(fn($b) => [
                    'id' => $b->id,
                    'batch_number' => $b->batch_number,
                    'current_qty' => $b->current_qty,
                    'expiry_date' => $b->expiry_date?->format('Y-m-d'),
                    'cost_price' => $b->cost_price,
                ]),
            ]);
        }

        return $result;
    }

    /**
     * Get pending requisitions for a store (as source - need to fulfill)
     *
     * @param int $storeId
     * @return Collection
     */
    public function getPendingFulfillment(int $storeId): Collection
    {
        return StoreRequisition::query()
            ->fromStore($storeId)
            ->fulfillable()
            ->with(['items.product', 'items.packaging', 'toStore', 'requester'])
            ->orderBy('approved_at', 'asc')
            ->get();
    }

    /**
     * Get pending approval requisitions
     *
     * @return Collection
     */
    public function getPendingApproval(): Collection
    {
        return StoreRequisition::pending()
            ->with(['items.product', 'items.packaging', 'fromStore', 'toStore', 'requester'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get requisitions for a store (as destination - my requests)
     *
     * @param int $storeId
     * @return Collection
     */
    public function getMyRequisitions(int $storeId): Collection
    {
        return StoreRequisition::query()
            ->toStore($storeId)
            ->with(['items.product', 'items.packaging', 'fromStore', 'requester', 'approver', 'fulfiller'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get requisition statistics
     *
     * @param int|null $storeId
     * @return array
     */
    public function getStatistics(?int $storeId = null): array
    {
        $query = StoreRequisition::query();

        if ($storeId) {
            $query->where(function ($q) use ($storeId) {
                $q->where('from_store_id', $storeId)
                  ->orWhere('to_store_id', $storeId);
            });
        }

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->pending()->count(),
            'approved' => (clone $query)->approved()->count(),
            'fulfilled' => (clone $query)->where('status', StoreRequisition::STATUS_FULFILLED)->count(),
            'rejected' => (clone $query)->where('status', StoreRequisition::STATUS_REJECTED)->count(),
        ];
    }
}
