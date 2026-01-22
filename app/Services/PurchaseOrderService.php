<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\Expense;
use App\Models\StoreStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Service: PurchaseOrderService
 *
 * Plan Reference: Phase 3 - Services
 * Purpose: Handle purchase order lifecycle management
 *
 * Workflow:
 * 1. Create PO (draft) with items
 * 2. Submit for approval
 * 3. Approve PO
 * 4. Receive items (creates batches)
 * 5. Create expense record
 *
 * Related Models: PurchaseOrder, PurchaseOrderItem, StockBatch, Expense
 * Related Files:
 * - app/Http/Controllers/PurchaseOrderController.php
 * - app/Services/StockService.php
 */
class PurchaseOrderService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Create a new purchase order
     *
     * @param array $data PO header data
     * @param array $items Array of items [['product_id', 'ordered_qty', 'unit_cost'], ...]
     * @return PurchaseOrder
     */
    public function create(array $data, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items) {
            $po = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'target_store_id' => $data['target_store_id'],
                'status' => PurchaseOrder::STATUS_DRAFT,
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $itemData) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $itemData['product_id'],
                    'ordered_qty' => $itemData['ordered_qty'],
                    'unit_cost' => $itemData['unit_cost'],
                    'status' => PurchaseOrderItem::STATUS_PENDING,
                ]);
            }

            $po->updateTotal();

            return $po->fresh(['items', 'items.product', 'supplier', 'targetStore']);
        });
    }

    /**
     * Update an existing purchase order
     *
     * @param PurchaseOrder $po
     * @param array $data PO header data
     * @param array|null $items Array of items (optional)
     * @return PurchaseOrder
     */
    public function update(PurchaseOrder $po, array $data, ?array $items = null): PurchaseOrder
    {
        if (!$po->canEdit()) {
            throw new \Exception("Purchase order cannot be edited in its current status");
        }

        return DB::transaction(function () use ($po, $data, $items) {
            $po->update([
                'supplier_id' => $data['supplier_id'] ?? $po->supplier_id,
                'target_store_id' => $data['target_store_id'] ?? $po->target_store_id,
                'expected_date' => $data['expected_date'] ?? $po->expected_date,
                'notes' => $data['notes'] ?? $po->notes,
            ]);

            if ($items !== null) {
                // Remove existing items and recreate
                $po->items()->delete();

                foreach ($items as $itemData) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'product_id' => $itemData['product_id'],
                        'ordered_qty' => $itemData['ordered_qty'],
                        'unit_cost' => $itemData['unit_cost'],
                        'status' => PurchaseOrderItem::STATUS_PENDING,
                    ]);
                }
            }

            $po->updateTotal();

            return $po->fresh(['items', 'items.product', 'supplier', 'targetStore']);
        });
    }

    /**
     * Add item to an existing PO
     *
     * @param PurchaseOrder $po
     * @param array $itemData
     * @return PurchaseOrderItem
     */
    public function addItem(PurchaseOrder $po, array $itemData): PurchaseOrderItem
    {
        if (!$po->canEdit()) {
            throw new \Exception("Cannot add items to this purchase order");
        }

        return DB::transaction(function () use ($po, $itemData) {
            $item = PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => $itemData['product_id'],
                'ordered_qty' => $itemData['ordered_qty'],
                'unit_cost' => $itemData['unit_cost'],
                'status' => PurchaseOrderItem::STATUS_PENDING,
            ]);

            $po->updateTotal();

            return $item;
        });
    }

    /**
     * Remove item from PO
     *
     * @param PurchaseOrderItem $item
     * @return bool
     */
    public function removeItem(PurchaseOrderItem $item): bool
    {
        $po = $item->purchaseOrder;

        if (!$po->canEdit()) {
            throw new \Exception("Cannot remove items from this purchase order");
        }

        return DB::transaction(function () use ($item, $po) {
            $item->delete();
            $po->updateTotal();
            return true;
        });
    }

    /**
     * Submit PO for approval
     *
     * @param PurchaseOrder $po
     * @return PurchaseOrder
     */
    public function submit(PurchaseOrder $po): PurchaseOrder
    {
        if (!$po->canSubmit()) {
            throw new \Exception("Purchase order cannot be submitted");
        }

        $po->status = PurchaseOrder::STATUS_SUBMITTED;
        $po->save();

        return $po;
    }

    /**
     * Approve PO
     *
     * @param PurchaseOrder $po
     * @param string|null $notes Approval notes
     * @return PurchaseOrder
     */
    public function approve(PurchaseOrder $po, ?string $notes = null): PurchaseOrder
    {
        if (!$po->canApprove()) {
            throw new \Exception("Purchase order cannot be approved");
        }

        $po->status = PurchaseOrder::STATUS_APPROVED;
        $po->approved_by = auth()->id();
        $po->approval_notes = $notes;
        $po->save();

        return $po;
    }

    /**
     * Reject/Cancel PO
     *
     * @param PurchaseOrder $po
     * @param string|null $reason
     * @return PurchaseOrder
     */
    public function cancel(PurchaseOrder $po, ?string $reason = null): PurchaseOrder
    {
        if (!$po->canCancel()) {
            throw new \Exception("Purchase order cannot be cancelled");
        }

        $po->status = PurchaseOrder::STATUS_CANCELLED;
        $po->notes = $reason ? ($po->notes . "\nCancellation reason: " . $reason) : $po->notes;
        $po->save();

        // Cancel all items
        $po->items()->update(['status' => PurchaseOrderItem::STATUS_CANCELLED]);

        return $po;
    }

    /**
     * Receive items from PO
     *
     * @param PurchaseOrder $po
     * @param array $receivedItems Array of ['item_id' => ['qty' => X, 'actual_cost' => Y, 'expiry_date' => Z, 'batch_number' => W]]
     * @return array Created batches
     */
    public function receiveItems(PurchaseOrder $po, array $receivedItems): array
    {
        if (!$po->canReceive()) {
            throw new \Exception("Cannot receive items for this purchase order");
        }

        return DB::transaction(function () use ($po, $receivedItems) {
            $createdBatches = [];
            $allFullyReceived = true;

            foreach ($receivedItems as $itemId => $receiveData) {
                $item = PurchaseOrderItem::findOrFail($itemId);

                if ($item->purchase_order_id !== $po->id) {
                    throw new \Exception("Item does not belong to this purchase order");
                }

                $qty = $receiveData['qty'] ?? 0;
                if ($qty <= 0) continue;

                $actualCost = $receiveData['actual_cost'] ?? $item->unit_cost;

                // Create stock batch
                $batch = $this->stockService->createBatch([
                    'product_id' => $item->product_id,
                    'store_id' => $po->target_store_id,
                    'qty' => $qty,
                    'cost_price' => $actualCost,
                    'expiry_date' => $receiveData['expiry_date'] ?? null,
                    'batch_number' => $receiveData['batch_number'] ?? null,
                    'source' => StockBatch::SOURCE_PURCHASE_ORDER,
                    'purchase_order_item_id' => $item->id,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => $po->id,
                    'notes' => "Received from PO: {$po->po_number}",
                ]);

                $createdBatches[] = $batch;

                // Update item received qty
                $item->receive($qty, $actualCost);

                if (!$item->isFullyReceived()) {
                    $allFullyReceived = false;
                }
            }

            // Check if any items are still not fully received
            $pendingItems = $po->items()->where('status', '!=', PurchaseOrderItem::STATUS_RECEIVED)->count();

            if ($pendingItems === 0 || $allFullyReceived) {
                $po->status = PurchaseOrder::STATUS_RECEIVED;
                $po->received_date = now();
            } else {
                $po->status = PurchaseOrder::STATUS_PARTIAL;
            }

            // Recalculate total based on actual costs
            $actualTotal = $po->items->sum(function ($item) {
                $cost = $item->actual_unit_cost ?? $item->unit_cost;
                return $item->received_qty * $cost;
            });

            $po->total_amount = $actualTotal;
            $po->save();

            // Create expense record if fully received
            if ($po->status === PurchaseOrder::STATUS_RECEIVED) {
                $this->createExpenseFromPO($po);
            }

            return $createdBatches;
        });
    }

    /**
     * Create expense record from fully received PO
     *
     * @param PurchaseOrder $po
     * @return Expense
     */
    protected function createExpenseFromPO(PurchaseOrder $po): Expense
    {
        // Check if expense already exists
        $existingExpense = Expense::where('reference_type', PurchaseOrder::class)
            ->where('reference_id', $po->id)
            ->first();

        if ($existingExpense) {
            // Update the amount if it changed
            $existingExpense->amount = $po->total_amount;
            $existingExpense->save();
            return $existingExpense;
        }

        return Expense::createFromPurchaseOrder($po);
    }

    /**
     * Get POs pending approval
     *
     * @return Collection
     */
    public function getPendingApproval(): Collection
    {
        return PurchaseOrder::submitted()
            ->with(['supplier', 'targetStore', 'creator', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get POs ready for receiving
     *
     * @return Collection
     */
    public function getReadyToReceive(): Collection
    {
        return PurchaseOrder::receivable()
            ->with(['supplier', 'targetStore', 'items.product'])
            ->orderBy('expected_date', 'asc')
            ->get();
    }

    /**
     * Get PO summary statistics
     *
     * @param string|null $period 'today', 'week', 'month', 'year'
     * @return array
     */
    public function getStatistics(?string $period = 'month'): array
    {
        $query = PurchaseOrder::query();

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
        }

        return [
            'total_count' => $query->count(),
            'draft_count' => (clone $query)->draft()->count(),
            'pending_approval' => (clone $query)->submitted()->count(),
            'approved' => (clone $query)->approved()->count(),
            'received' => (clone $query)->where('status', PurchaseOrder::STATUS_RECEIVED)->count(),
            'total_value' => (clone $query)->where('status', PurchaseOrder::STATUS_RECEIVED)->sum('total_amount'),
        ];
    }
}
