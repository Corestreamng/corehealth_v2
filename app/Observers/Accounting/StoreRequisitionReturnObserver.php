<?php

namespace App\Observers\Accounting;

use App\Models\StoreRequisitionReturn;
use App\Services\RequisitionService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Store Requisition Return Observer
 *
 * Handles stock movement when a requisition return is approved.
 * NO journal entry is created — this is an internal inter-store movement at cost.
 *
 * On Approved:
 *   1. Deduct qty_returned from source_store_id (the store returning items)
 *      → StockBatchTransaction TYPE_REQ_RETURN (out)
 *   2. If restock = true: add qty_returned back to destination_store_id (the origin store)
 *      → StockBatchTransaction TYPE_REQ_RETURN (in, via addStock)
 */
class StoreRequisitionReturnObserver
{
    public function updated(StoreRequisitionReturn $return): void
    {
        if (!($return->isDirty('status') && $return->status === 'approved')) {
            return;
        }

        try {
            if ($return->stock_adjusted) {
                Log::info('StoreRequisitionReturnObserver: Stock already adjusted, skipping', ['return_id' => $return->id]);
                return;
            }

            DB::transaction(function () use ($return) {
                $service = App::make(RequisitionService::class);
                $service->returnItems($return);

                $return->update([
                    'stock_adjusted'    => true,
                    'stock_adjusted_at' => now(),
                ]);
            });

        } catch (\Exception $e) {
            Log::error('StoreRequisitionReturnObserver: Failed', [
                'return_id' => $return->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
