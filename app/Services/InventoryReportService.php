<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreRequisitionItem;
use App\Models\ProductRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    /**
     * Get aggregate summary data.
     * 
     * @param int|array $storeIds Target store ID(s)
     * @param string $mode 'given' (outbound) or 'received' (inbound)
     * @param string $groupBy 'category' or 'destination'
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
            $reqItems = StoreRequisitionItem::with(['product.category', 'requisition.toStore'])
                ->whereHas('requisition', function ($q) use ($storeIds, $start, $end) {
                    $q->whereIn('from_store_id', $storeIds)
                      ->where('status', 'fulfilled')
                      ->whereBetween('updated_at', [$start, $end]);
                })
                ->get();

            $this->aggregateRequisitions($reqItems, $aggregates, $groupBy, 'toStore');

            // 2. Dispenses made FROM these stores
            $dispenses = ProductRequest::with(['product.category', 'encounter.service', 'encounter.admission_request.preferredWard'])
                ->whereIn('dispensed_from_store_id', $storeIds)
                ->where('status', 'dispensed')
                ->whereBetween('dispense_date', [$start, $end])
                ->get();

            $this->aggregateDispenses($dispenses, $aggregates, $groupBy);
        } else {
            // Received mode
            // 1. Requisitions fulfilled INTO these stores
            $reqItems = StoreRequisitionItem::with(['product.category', 'requisition.fromStore'])
                ->whereHas('requisition', function ($q) use ($storeIds, $start, $end) {
                    $q->whereIn('to_store_id', $storeIds)
                      ->where('status', 'fulfilled')
                      ->whereBetween('updated_at', [$start, $end]);
                })
                ->get();

            $this->aggregateRequisitions($reqItems, $aggregates, $groupBy, 'fromStore');
            
            // Note: For full accuracy of "Received" we might also need to look at Purchase Orders
            // or Stock Batches if the central store receives directly from vendors.
            // For now, based on user requirements, we focus on internal movement & dispenses.
        }

        // Format for output
        $results = [];
        foreach ($aggregates as $key => $data) {
            $results[] = [
                'grouping_key' => $key,
                'total_qty' => $data['qty'],
                'total_value' => $data['value'],
            ];
        }

        // Sort descending by value
        usort($results, fn($a, $b) => $b['total_value'] <=> $a['total_value']);

        return $results;
    }

    public function getDrillDownData($storeIds, string $mode, string $groupBy, string $groupKey, string $startDate, string $endDate): array
    {
        $storeIds = (array) $storeIds;
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        
        $details = [];

        if ($mode === 'given') {
            $reqItems = StoreRequisitionItem::with(['product', 'sourceBatch', 'requisition.toStore', 'product.category'])
                ->whereHas('requisition', function ($q) use ($storeIds, $start, $end) {
                    $q->whereIn('from_store_id', $storeIds)
                      ->where('status', 'fulfilled')
                      ->whereBetween('updated_at', [$start, $end]);
                })
                ->get();

            $this->extractDrillDownRequisitions($reqItems, $details, $groupBy, $groupKey, 'toStore');

            $dispenses = ProductRequest::with(['product', 'dispensedFromBatch', 'encounter.service', 'encounter.admission_request.preferredWard', 'product.category'])
                ->whereIn('dispensed_from_store_id', $storeIds)
                ->where('status', 'dispensed')
                ->whereBetween('dispense_date', [$start, $end])
                ->get();

            $this->extractDrillDownDispenses($dispenses, $details, $groupBy, $groupKey);
        } else {
            $reqItems = StoreRequisitionItem::with(['product', 'destinationBatch', 'requisition.fromStore', 'product.category'])
                ->whereHas('requisition', function ($q) use ($storeIds, $start, $end) {
                    $q->whereIn('to_store_id', $storeIds)
                      ->where('status', 'fulfilled')
                      ->whereBetween('updated_at', [$start, $end]);
                })
                ->get();

            $this->extractDrillDownRequisitions($reqItems, $details, $groupBy, $groupKey, 'fromStore');
        }

        return array_values($details);
    }

    private function aggregateRequisitions($items, &$aggregates, $groupBy, $storeRelation)
    {
        foreach ($items as $item) {
            $qty = $item->fulfilled_qty ?? 0;
            if ($qty <= 0) continue;
            
            $cost = $item->product->cost_price ?? 0;
            $val = $qty * $cost;

            if ($groupBy === 'category') {
                $key = $item->product->category->category_name ?? 'Uncategorized';
            } else {
                $key = $item->requisition->$storeRelation->store_name ?? 'Unknown Store';
            }

            if (!isset($aggregates[$key])) {
                $aggregates[$key] = ['qty' => 0, 'value' => 0];
            }
            $aggregates[$key]['qty'] += $qty;
            $aggregates[$key]['value'] += $val;
        }
    }

    private function aggregateDispenses($items, &$aggregates, $groupBy)
    {
        foreach ($items as $item) {
            $qty = $item->qty ?? 0;
            if ($qty <= 0) continue;
            
            $cost = $item->product->cost_price ?? 0;
            $val = $qty * $cost;

            if ($groupBy === 'category') {
                $key = $item->product->category->category_name ?? 'Uncategorized';
            } else {
                $key = $this->resolveDispenseDestination($item);
            }

            if (!isset($aggregates[$key])) {
                $aggregates[$key] = ['qty' => 0, 'value' => 0];
            }
            $aggregates[$key]['qty'] += $qty;
            $aggregates[$key]['value'] += $val;
        }
    }

    private function extractDrillDownRequisitions($items, &$details, $groupBy, $targetKey, $storeRelation)
    {
        foreach ($items as $item) {
            $qty = $item->fulfilled_qty ?? 0;
            if ($qty <= 0) continue;

            $key = ($groupBy === 'category') 
                ? ($item->product->category->category_name ?? 'Uncategorized')
                : ($item->requisition->$storeRelation->store_name ?? 'Unknown Store');

            if (strtolower($key) !== strtolower($targetKey)) continue;

            $batch = $storeRelation === 'toStore' ? $item->sourceBatch : $item->destinationBatch;
            $cost = $item->product->cost_price ?? 0;

            $this->addDrillDownRow($details, [
                'type' => 'Requisition',
                'date' => $item->updated_at->format('Y-m-d H:i'),
                'product_name' => $item->product->product_name ?? 'Unknown',
                'packaging' => $item->product->packaging ?? 'Unit',
                'batch_number' => $batch->batch_number ?? 'N/A',
                'expiry_date' => $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : 'N/A',
                'qty' => $qty,
                'cost_price' => $cost,
                'total_value' => $qty * $cost,
            ]);
        }
    }

    private function extractDrillDownDispenses($items, &$details, $groupBy, $targetKey)
    {
        foreach ($items as $item) {
            $qty = $item->qty ?? 0;
            if ($qty <= 0) continue;

            $key = ($groupBy === 'category') 
                ? ($item->product->category->category_name ?? 'Uncategorized')
                : $this->resolveDispenseDestination($item);

            if (strtolower($key) !== strtolower($targetKey)) continue;

            $batch = $item->dispensedFromBatch;
            $cost = $item->product->cost_price ?? 0;

            $this->addDrillDownRow($details, [
                'type' => 'Dispense',
                'date' => $item->dispense_date ? $item->dispense_date->format('Y-m-d H:i') : $item->created_at->format('Y-m-d H:i'),
                'product_name' => $item->product->product_name ?? 'Unknown',
                'packaging' => $item->product->packaging ?? 'Unit',
                'batch_number' => $batch->batch_number ?? 'N/A',
                'expiry_date' => $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : 'N/A',
                'qty' => $qty,
                'cost_price' => $cost,
                'total_value' => $qty * $cost,
            ]);
        }
    }

    private function addDrillDownRow(&$details, $row)
    {
        // Group by product and batch to make it dense and clean
        $hash = md5($row['product_name'] . $row['batch_number'] . $row['cost_price']);
        if (!isset($details[$hash])) {
            $details[$hash] = $row;
        } else {
            $details[$hash]['qty'] += $row['qty'];
            $details[$hash]['total_value'] += $row['total_value'];
        }
    }

    private function resolveDispenseDestination($item): string
    {
        if (!$item->encounter) return 'General Outpatient';
        
        $enc = $item->encounter;
        
        // Is it linked to a Procedure?
        if (class_exists(\App\Models\Procedure::class)) {
            $isProc = \App\Models\Procedure::where('encounter_id', $enc->id)->exists();
            if ($isProc) return 'Theater / Procedure Room';
        }

        // Is it linked to Maternity?
        if (class_exists(\App\Models\MaternityEncounterLink::class)) {
            $isMat = \App\Models\MaternityEncounterLink::where('encounter_id', $enc->id)->exists();
            if ($isMat) return 'Maternity Clinic';
        }

        // Is it linked to a Ward (Admission)?
        if ($enc->admission_request && $enc->admission_request->preferredWard) {
            return 'Ward: ' . $enc->admission_request->preferredWard->name;
        }

        // Is it linked to a specific Service/Clinic?
        if ($enc->service) {
            return 'Clinic: ' . $enc->service->name;
        }

        return 'General Outpatient';
    }
}
