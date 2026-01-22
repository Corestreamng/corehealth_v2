<?php

namespace App\Services;

use App\Models\StoreRequisition;
use App\Models\StoreRequisitionItem;
use App\Models\StockBatch;
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

        return DB::transaction(function () use ($requisition, $approvedQtys, $notes) {
            // Update approved quantities for each item
            foreach ($requisition->items as $item) {
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
     * Fulfill requisition items
     *
     * @param StoreRequisition $requisition
     * @param array $fulfillments Array of ['item_id' => ['qty' => X, 'batch_id' => Y]]
     *                            batch_id is optional (uses FIFO if not specified)
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

                $qty = $fulfillData['qty'] ?? 0;
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

                if (!$item->isFullyFulfilled()) {
                    $allFullyFulfilled = false;
                }
            }

            // Update requisition status
            $pendingItems = $requisition->items()
                ->whereIn('status', [StoreRequisitionItem::STATUS_APPROVED, StoreRequisitionItem::STATUS_PARTIAL])
                ->count();

            if ($pendingItems === 0) {
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
        return StoreRequisition::fromStore($storeId)
            ->fulfillable()
            ->with(['items.product', 'toStore', 'requester'])
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
            ->with(['items.product', 'fromStore', 'toStore', 'requester'])
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
        return StoreRequisition::toStore($storeId)
            ->with(['items.product', 'fromStore', 'requester', 'approver', 'fulfiller'])
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
