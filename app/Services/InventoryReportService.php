<?php

namespace App\Services;

use App\Models\HmoTariff;
use App\Models\ProductRequest;
use App\Models\StockBatch;
use App\Models\Store;
use App\Models\StoreRequisitionItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    /**
     * Preload average HMO tariff selling price across all HMOs for the given products.
     * Calculated as AVG(claims_amount + payable_amount) across all tariff rows for each product.
     *
     * @param array $productIds
     * @return array [product_id => float avg_selling_price]
     */
    protected function getProductTariffAvgPrices(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        return HmoTariff::whereIn('product_id', $productIds)
            ->whereNotNull('product_id')
            ->select('product_id', DB::raw('AVG(claims_amount + payable_amount) as avg_tariff_price'))
            ->groupBy('product_id')
            ->pluck('avg_tariff_price', 'product_id')
            ->map(fn ($p) => (float) $p)
            ->toArray();
    }

    /**
     * Resolve product selling price based on average HMO tariff (claims_amount + payable_amount) across the board.
     * Falls back to product price current_sale_price if no tariff exists, or 0.
     */
    protected function resolveProductSellingPrice($product, array $tariffAvgMap = [], $fallbackOverride = null): float
    {
        if ($fallbackOverride !== null && (float) $fallbackOverride > 0) {
            return (float) $fallbackOverride;
        }

        $productId = is_numeric($product) ? (int) $product : ($product?->id ?? null);
        if ($productId && isset($tariffAvgMap[$productId]) && (float) $tariffAvgMap[$productId] > 0) {
            return (float) $tariffAvgMap[$productId];
        }

        return (float) ($product?->price?->current_sale_price ?? 0);
    }

    /**
     * Resolve batch cost price with 0 as strict fallback.
     */
    protected function resolveBatchCostPrice($batch): float
    {
        if (!$batch) {
            return 0.0;
        }

        return (float) ($batch->cost_price ?? 0);
    }

    /**
     * Get aggregate summary data.
     *
     * @param int|array $storeIds Target store ID(s)
     * @param string $mode 'given' (outbound) or 'received' (inbound)
     * @param string $groupBy 'category', 'destination', or 'product'
     * @param string $startDate 'Y-m-d'
     * @param string $endDate 'Y-m-d'
     */
    public function getSummaryData($storeIds, string $mode, string $groupBy, string $startDate, string $endDate): array
    {
        $storeIds = (array) $storeIds;
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $aggregates = [];

        if ($mode === 'given') {
            // 1. Requisitions fulfilled FROM these stores
            $reqItems = $this->buildRequisitionsQuery($storeIds, $start, $end, 'toStore')->get();

            // 2. Dispenses made FROM these stores
            $dispenses = $this->buildDispensesQuery($storeIds, $start, $end)->get();

            $productIds = $reqItems->pluck('product_id')
                ->merge($dispenses->pluck('product_id'))
                ->filter()
                ->unique()
                ->toArray();
            $tariffAvgMap = $this->getProductTariffAvgPrices($productIds);

            $this->aggregateRequisitions($reqItems, $aggregates, $groupBy, 'toStore', $tariffAvgMap, 'given');
            $this->aggregateDispenses($dispenses, $aggregates, $groupBy, $tariffAvgMap);
        } else {
            // Received mode:
            // 1. Requisitions fulfilled INTO these stores
            $reqItems = $this->buildRequisitionsQuery($storeIds, $start, $end, 'fromStore')->get();

            // 2. Direct stock batches (PO created and Manual entry) received INTO these stores
            $directBatches = $this->buildDirectBatchesQuery($storeIds, $start, $end)->get();

            $productIds = $reqItems->pluck('product_id')
                ->merge($directBatches->pluck('product_id'))
                ->filter()
                ->unique()
                ->toArray();
            $tariffAvgMap = $this->getProductTariffAvgPrices($productIds);

            $this->aggregateRequisitions($reqItems, $aggregates, $groupBy, 'fromStore', $tariffAvgMap, 'received');
            $this->aggregateDirectBatches($directBatches, $aggregates, $groupBy, $tariffAvgMap);
        }

        // Format for output
        $results = [];
        foreach ($aggregates as $key => $data) {
            $results[] = [
                'grouping_key' => $key,
                'total_qty' => $data['qty'],
                'total_value' => $data['value'],
                'cash_revenue' => $data['cash_revenue'] ?? 0,
                'claims_revenue' => $data['claims_revenue'] ?? 0,
                'potential_revenue' => $data['potential_revenue'] ?? 0,
                'profit' => $data['profit'] ?? 0,
                'channels' => $data['channels'] ?? [],
            ];
        }

        // Sort descending by value
        usort($results, fn ($a, $b) => $b['total_value'] <=> $a['total_value']);

        return $results;
    }

    public function getDrillDownData($storeIds, string $mode, string $groupBy, string $groupKey, string $startDate, string $endDate): array
    {
        $storeIds = (array) $storeIds;
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $details = [];

        if ($mode === 'given') {
            $reqItems = $this->buildRequisitionsQuery($storeIds, $start, $end, 'toStore')->get();
            $dispenses = $this->buildDispensesQuery($storeIds, $start, $end)->get();

            $productIds = $reqItems->pluck('product_id')
                ->merge($dispenses->pluck('product_id'))
                ->filter()
                ->unique()
                ->toArray();
            $tariffAvgMap = $this->getProductTariffAvgPrices($productIds);

            $this->extractDrillDownRequisitions($reqItems, $details, $groupBy, $groupKey, 'toStore', $tariffAvgMap, 'given');
            $this->extractDrillDownDispenses($dispenses, $details, $groupBy, $groupKey, $tariffAvgMap);
        } else {
            $reqItems = $this->buildRequisitionsQuery($storeIds, $start, $end, 'fromStore')->get();
            $directBatches = $this->buildDirectBatchesQuery($storeIds, $start, $end)->get();

            $productIds = $reqItems->pluck('product_id')
                ->merge($directBatches->pluck('product_id'))
                ->filter()
                ->unique()
                ->toArray();
            $tariffAvgMap = $this->getProductTariffAvgPrices($productIds);

            $this->extractDrillDownRequisitions($reqItems, $details, $groupBy, $groupKey, 'fromStore', $tariffAvgMap, 'received');
            $this->extractDrillDownDirectBatches($directBatches, $details, $groupBy, $groupKey, $tariffAvgMap);
        }

        return array_values($details);
    }

    private function buildRequisitionsQuery(array $storeIds, Carbon $start, Carbon $end, string $storeRelation)
    {
        $column = $storeRelation === 'toStore' ? 'from_store_id' : 'to_store_id';

        return StoreRequisitionItem::with([
            'product.category',
            'product.price',
            'requisition.' . $storeRelation,
            'sourceBatch',
            'destinationBatch',
        ])
        ->whereHas('requisition', function ($q) use ($column, $storeIds, $start, $end) {
            $q->whereIn($column, $storeIds)
              ->whereIn('status', ['fulfilled', 'partial'])
              ->whereBetween('updated_at', [$start, $end]);
        })
        ->where('fulfilled_qty', '>', 0);
    }

    private function buildDispensesQuery(array $storeIds, Carbon $start, Carbon $end)
    {
        $isPharmacyStore = Store::whereIn('id', $storeIds)
            ->where(function ($sq) {
                $sq->where('is_default', 1)
                   ->orWhere('store_type', 'pharmacy')
                   ->orWhere('id', 2);
            })
            ->exists();

        return ProductRequest::with([
            'product.category',
            'product.price',
            'encounter.service',
            'encounter.admission_request.preferredWard',
            'dispensedFromBatch',
            'productOrServiceRequest',
        ])
        ->where(function ($q) use ($storeIds, $isPharmacyStore) {
            $q->whereIn('dispensed_from_store_id', $storeIds);
            if ($isPharmacyStore) {
                $q->orWhere(function ($sub) use ($storeIds) {
                    $sub->whereNull('dispensed_from_store_id')
                        ->where(function ($sub2) use ($storeIds) {
                            $sub2->whereHas('dispensedFromBatch', function ($bq) use ($storeIds) {
                                $bq->whereIn('store_id', $storeIds);
                            })->orWhereNull('dispensed_from_batch_id');
                        });
                });
            }
        })
        ->where(function ($q) {
            $q->where('status', 3)->orWhere('status', 'dispensed');
        })
        ->where(function ($q) use ($start, $end) {
            $q->whereBetween('dispense_date', [$start, $end])
              ->orWhere(function ($sub) use ($start, $end) {
                  $sub->whereNull('dispense_date')
                      ->whereBetween('updated_at', [$start, $end]);
              });
        });
    }

    private function buildDirectBatchesQuery(array $storeIds, Carbon $start, Carbon $end)
    {
        return StockBatch::with([
            'product.category',
            'product.price',
            'supplier',
            'purchaseOrderItem.purchaseOrder.supplier',
        ])
        ->whereIn('store_id', $storeIds)
        ->where(function ($q) {
            $q->whereIn('source', [
                StockBatch::SOURCE_PURCHASE_ORDER,
                StockBatch::SOURCE_MANUAL,
                StockBatch::SOURCE_OPENING_STOCK,
            ])
            ->orWhereNull('source');
        })
        ->where('source', '!=', StockBatch::SOURCE_TRANSFER_IN)
        ->whereNull('source_requisition_id')
        ->where(function ($q) use ($start, $end) {
            $q->whereBetween('received_date', [$start->toDateString(), $end->toDateString()])
              ->orWhere(function ($sub) use ($start, $end) {
                  $sub->whereNull('received_date')
                      ->whereBetween('created_at', [$start, $end]);
              });
        })
        ->where('initial_qty', '>', 0);
    }

    protected function resolveBatchReceiptType($batch): string
    {
        if (!empty($batch->is_donation)) {
            return 'Donation';
        }

        if ($batch->source === StockBatch::SOURCE_PURCHASE_ORDER || !empty($batch->purchase_order_item_id)) {
            return 'PO Receipt';
        }

        return 'Manual Batch';
    }

    protected function resolveBatchSourceKey($batch, string $receiptType): string
    {
        if ($receiptType === 'Donation') {
            $donorName = $batch->donor?->company_name ?? $batch->supplier?->company_name ?? $batch->donor?->name ?? $batch->supplier?->name;

            return $donorName ? "Donation: Direct ({$donorName})" : "Donation: Direct Batch";
        }

        if ($receiptType === 'PO Receipt') {
            $po = $batch->purchaseOrderItem?->purchaseOrder;
            $poNumber = $po?->po_number ?? 'PO';
            $supplierName = $po?->supplier?->company_name ?? $po?->supplier?->name ?? $batch->supplier?->company_name ?? $batch->supplier?->name ?? null;

            return $supplierName ? "PO Receipt: {$poNumber} ({$supplierName})" : "PO Receipt: {$poNumber}";
        }

        $supplierName = $batch->supplier?->company_name ?? $batch->supplier?->name;

        return $supplierName ? "Manual Entry: Direct ({$supplierName})" : "Manual Entry: Direct Batch";
    }

    private function aggregateRequisitions($items, &$aggregates, $groupBy, $storeRelation, array $tariffAvgMap = [], string $mode = 'given')
    {
        foreach ($items as $item) {
            $qty = $item->fulfilled_qty ?? 0;
            if ($qty <= 0) {
                continue;
            }

            $batch = $storeRelation === 'toStore' ? $item->sourceBatch : ($item->destinationBatch ?? $item->sourceBatch);
            $cost = $this->resolveBatchCostPrice($batch);
            $val = $qty * $cost;

            $salePrice = $this->resolveProductSellingPrice($item->product, $tariffAvgMap);
            $potentialRev = $qty * $salePrice;

            if ($groupBy === 'category') {
                $key = $item->product?->category?->category_name ?? 'Uncategorized';
            } elseif ($groupBy === 'product') {
                $key = $item->product?->product_name ?? 'Unknown Product';
            } else {
                if ($mode === 'received') {
                    $key = 'Requisition: ' . ($item->requisition?->$storeRelation?->store_name ?? 'Unknown Store');
                } else {
                    $key = $item->requisition?->$storeRelation?->store_name ?? 'Unknown Store';
                }
            }

            if (!isset($aggregates[$key])) {
                $aggregates[$key] = [
                    'qty' => 0,
                    'value' => 0,
                    'cash_revenue' => 0,
                    'claims_revenue' => 0,
                    'potential_revenue' => 0,
                    'profit' => 0,
                    'channels' => [],
                ];
            }
            $aggregates[$key]['qty'] += $qty;
            $aggregates[$key]['value'] += $val;
            $aggregates[$key]['potential_revenue'] += $potentialRev;
            $aggregates[$key]['profit'] += ($potentialRev - $val);
            $aggregates[$key]['channels']['Requisition'] = ($aggregates[$key]['channels']['Requisition'] ?? 0) + $qty;
        }
    }

    private function aggregateDispenses($items, &$aggregates, $groupBy, array $tariffAvgMap = [])
    {
        foreach ($items as $item) {
            $qty = $item->qty ?? 0;
            if ($qty <= 0) {
                continue;
            }

            $batch = $item->dispensedFromBatch;
            $cost = $this->resolveBatchCostPrice($batch);
            $val = $qty * $cost;

            $cashRev = 0;
            $claimsRev = 0;
            $potentialRev = 0;
            $psr = $item->productOrServiceRequest;

            if ($psr) {
                $cashRev = (float) ($psr->payable_amount ?? 0);
                $claimsRev = (float) ($psr->claims_amount ?? 0);
            } else {
                $salePrice = $this->resolveProductSellingPrice(
                    $item->product,
                    $tariffAvgMap,
                    $item->price_override ?? $item->price_original
                );
                $potentialRev = $qty * $salePrice;
            }

            if ($groupBy === 'category') {
                $key = $item->product?->category?->category_name ?? 'Uncategorized';
            } elseif ($groupBy === 'product') {
                $key = $item->product?->product_name ?? $item->free_form_name ?? $item->item_name ?? 'Unknown Product';
            } else {
                $key = $this->resolveDispenseDestination($item);
            }

            if (!isset($aggregates[$key])) {
                $aggregates[$key] = [
                    'qty' => 0,
                    'value' => 0,
                    'cash_revenue' => 0,
                    'claims_revenue' => 0,
                    'potential_revenue' => 0,
                    'profit' => 0,
                    'channels' => [],
                ];
            }
            $aggregates[$key]['qty'] += $qty;
            $aggregates[$key]['value'] += $val;
            $aggregates[$key]['cash_revenue'] += $cashRev;
            $aggregates[$key]['claims_revenue'] += $claimsRev;
            $aggregates[$key]['potential_revenue'] += $potentialRev;
            $totalRevenue = $cashRev + $claimsRev + $potentialRev;
            $aggregates[$key]['profit'] += ($totalRevenue - $val);
            $aggregates[$key]['channels']['Dispense'] = ($aggregates[$key]['channels']['Dispense'] ?? 0) + $qty;
        }
    }

    private function aggregateDirectBatches($batches, &$aggregates, $groupBy, array $tariffAvgMap = [])
    {
        foreach ($batches as $batch) {
            $qty = $batch->initial_qty ?? 0;
            if ($qty <= 0) {
                continue;
            }

            $cost = $this->resolveBatchCostPrice($batch);
            $val = $qty * $cost;

            $salePrice = $this->resolveProductSellingPrice($batch->product, $tariffAvgMap);
            $potentialRev = $qty * $salePrice;

            $receiptType = $this->resolveBatchReceiptType($batch);

            if ($groupBy === 'category') {
                $key = $batch->product?->category?->category_name ?? 'Uncategorized';
            } elseif ($groupBy === 'product') {
                $key = $batch->product?->product_name ?? 'Unknown Product';
            } else {
                $key = $this->resolveBatchSourceKey($batch, $receiptType);
            }

            if (!isset($aggregates[$key])) {
                $aggregates[$key] = [
                    'qty' => 0,
                    'value' => 0,
                    'cash_revenue' => 0,
                    'claims_revenue' => 0,
                    'potential_revenue' => 0,
                    'profit' => 0,
                    'channels' => [],
                ];
            }

            $aggregates[$key]['qty'] += $qty;
            $aggregates[$key]['value'] += $val;
            $aggregates[$key]['potential_revenue'] += $potentialRev;
            $aggregates[$key]['profit'] += ($potentialRev - $val);
            $aggregates[$key]['channels'][$receiptType] = ($aggregates[$key]['channels'][$receiptType] ?? 0) + $qty;
        }
    }

    private function extractDrillDownRequisitions($items, &$details, $groupBy, $targetKey, $storeRelation, array $tariffAvgMap = [], string $mode = 'given')
    {
        foreach ($items as $item) {
            $qty = $item->fulfilled_qty ?? 0;
            if ($qty <= 0) {
                continue;
            }

            if ($groupBy === 'category') {
                $key = $item->product?->category?->category_name ?? 'Uncategorized';
            } elseif ($groupBy === 'product') {
                $key = $item->product?->product_name ?? 'Unknown Product';
            } else {
                if ($mode === 'received') {
                    $key = 'Requisition: ' . ($item->requisition?->$storeRelation?->store_name ?? 'Unknown Store');
                } else {
                    $key = $item->requisition?->$storeRelation?->store_name ?? 'Unknown Store';
                }
            }

            if (strtolower($key) !== strtolower($targetKey)) {
                continue;
            }

            $batch = $storeRelation === 'toStore' ? $item->sourceBatch : ($item->destinationBatch ?? $item->sourceBatch);
            $cost = $this->resolveBatchCostPrice($batch);

            $salePrice = $this->resolveProductSellingPrice($item->product, $tariffAvgMap);
            $potentialRev = $qty * $salePrice;

            $this->addDrillDownRow($details, [
                'type' => 'Requisition',
                'date' => $item->updated_at->format('Y-m-d H:i'),
                'product_name' => $item->product?->product_name ?? 'Unknown',
                'packaging' => $item->product?->packaging ?? 'Unit',
                'batch_number' => $batch->batch_number ?? 'N/A',
                'expiry_date' => $batch?->expiry_date ? $batch->expiry_date->format('Y-m-d') : 'N/A',
                'qty' => $qty,
                'cost_price' => $cost,
                'total_value' => $qty * $cost,
                'cash_paid' => 0,
                'claims_paid' => 0,
                'total_revenue' => $potentialRev,
                'profit' => $potentialRev - ($qty * $cost),
            ]);
        }
    }

    private function extractDrillDownDispenses($items, &$details, $groupBy, $targetKey, array $tariffAvgMap = [])
    {
        foreach ($items as $item) {
            $qty = $item->qty ?? 0;
            if ($qty <= 0) {
                continue;
            }

            $key = ($groupBy === 'category')
                ? ($item->product?->category?->category_name ?? 'Uncategorized')
                : (($groupBy === 'product') ? ($item->product?->product_name ?? $item->free_form_name ?? $item->item_name ?? 'Unknown Product') : $this->resolveDispenseDestination($item));

            if (strtolower($key) !== strtolower($targetKey)) {
                continue;
            }

            $batch = $item->dispensedFromBatch;
            $cost = $this->resolveBatchCostPrice($batch);

            $cashRev = 0;
            $claimsRev = 0;
            $potentialRev = 0;
            $psr = $item->productOrServiceRequest;

            if ($psr) {
                $cashRev = (float) ($psr->payable_amount ?? 0);
                $claimsRev = (float) ($psr->claims_amount ?? 0);
            } else {
                $salePrice = $this->resolveProductSellingPrice(
                    $item->product,
                    $tariffAvgMap,
                    $item->price_override ?? $item->price_original
                );
                $potentialRev = $qty * $salePrice;
            }

            $totalRev = $cashRev + $claimsRev + $potentialRev;

            $this->addDrillDownRow($details, [
                'type' => 'Dispense',
                'date' => $item->dispense_date ? $item->dispense_date->format('Y-m-d H:i') : $item->created_at->format('Y-m-d H:i'),
                'product_name' => $item->product?->product_name ?? $item->free_form_name ?? $item->item_name ?? 'Unknown',
                'packaging' => $item->product?->packaging ?? 'Unit',
                'batch_number' => $batch->batch_number ?? 'N/A',
                'expiry_date' => $batch?->expiry_date ? $batch->expiry_date->format('Y-m-d') : 'N/A',
                'qty' => $qty,
                'cost_price' => $cost,
                'total_value' => $qty * $cost,
                'cash_paid' => $cashRev,
                'claims_paid' => $claimsRev,
                'total_revenue' => $totalRev,
                'profit' => $totalRev - ($qty * $cost),
            ]);
        }
    }

    private function extractDrillDownDirectBatches($batches, &$details, $groupBy, $targetKey, array $tariffAvgMap = [])
    {
        foreach ($batches as $batch) {
            $qty = $batch->initial_qty ?? 0;
            if ($qty <= 0) {
                continue;
            }

            $receiptType = $this->resolveBatchReceiptType($batch);

            if ($groupBy === 'category') {
                $key = $batch->product?->category?->category_name ?? 'Uncategorized';
            } elseif ($groupBy === 'product') {
                $key = $batch->product?->product_name ?? 'Unknown Product';
            } else {
                $key = $this->resolveBatchSourceKey($batch, $receiptType);
            }

            if (strtolower($key) !== strtolower($targetKey)) {
                continue;
            }

            $cost = $this->resolveBatchCostPrice($batch);
            $salePrice = $this->resolveProductSellingPrice($batch->product, $tariffAvgMap);
            $potentialRev = $qty * $salePrice;

            $dateStr = $batch->received_date
                ? $batch->received_date->format('Y-m-d')
                : ($batch->created_at ? $batch->created_at->format('Y-m-d H:i') : 'N/A');

            $this->addDrillDownRow($details, [
                'type' => $receiptType,
                'date' => $dateStr,
                'product_name' => $batch->product?->product_name ?? 'Unknown',
                'packaging' => $batch->product?->packaging ?? 'Unit',
                'batch_number' => $batch->batch_number ?? $batch->batch_name ?? 'N/A',
                'expiry_date' => $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : 'N/A',
                'qty' => $qty,
                'cost_price' => $cost,
                'total_value' => $qty * $cost,
                'cash_paid' => 0,
                'claims_paid' => 0,
                'total_revenue' => $potentialRev,
                'profit' => $potentialRev - ($qty * $cost),
            ]);
        }
    }

    private function addDrillDownRow(&$details, $row)
    {
        // Group by type, product and batch to make it dense and flag different receipt channels
        $hash = md5(($row['type'] ?? '') . '_' . $row['product_name'] . '_' . $row['batch_number'] . '_' . $row['cost_price']);
        if (!isset($details[$hash])) {
            $details[$hash] = $row;
        } else {
            $details[$hash]['qty'] += $row['qty'];
            $details[$hash]['total_value'] += $row['total_value'];
            $details[$hash]['cash_paid'] += $row['cash_paid'];
            $details[$hash]['claims_paid'] += $row['claims_paid'];
            $details[$hash]['total_revenue'] += $row['total_revenue'];
            $details[$hash]['profit'] += $row['profit'];
        }
    }

    private function resolveDispenseDestination($item): string
    {
        if (!$item->encounter) {
            return 'General Outpatient';
        }

        $enc = $item->encounter;

        // Is it linked to a Procedure?
        if (class_exists(\App\Models\Procedure::class)) {
            $isProc = \App\Models\Procedure::where('encounter_id', $enc->id)->exists();
            if ($isProc) {
                return 'Theater / Procedure Room';
            }
        }

        // Is it linked to Maternity?
        if (class_exists(\App\Models\MaternityEncounterLink::class)) {
            $isMat = \App\Models\MaternityEncounterLink::where('encounter_id', $enc->id)->exists();
            if ($isMat) {
                return 'Maternity Clinic';
            }
        }

        // Is it linked to a Ward (Admission)?
        if ($enc->admission_request && $enc->admission_request->preferredWard) {
            return 'Ward: ' . $enc->admission_request->preferredWard->name;
        }

        // Is it linked to a specific Service/Clinic?
        if ($enc->service) {
            $serviceName = $enc->service->service_name ?? $enc->service->name ?? '';

            return !empty(trim($serviceName)) ? 'Clinic: ' . $serviceName : 'General Outpatient';
        }

        return 'General Outpatient';
    }
}
