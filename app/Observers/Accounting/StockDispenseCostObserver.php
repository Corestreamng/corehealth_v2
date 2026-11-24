<?php

namespace App\Observers\Accounting;

use App\Models\StockBatchTransaction;
use App\Services\Accounting\InventoryCostService;
use Illuminate\Support\Facades\Log;

/**
 * Costs every stock issue to a patient as it happens (Dr cost of sales / Cr inventory at batch cost).
 * It can never stop a dispense: a failure is logged and the backfill command picks the issue up later.
 */
class StockDispenseCostObserver
{
    public function __construct(protected InventoryCostService $cost)
    {
    }

    public function created(StockBatchTransaction $tx): void
    {
        if ($tx->type !== StockBatchTransaction::TYPE_OUT || !in_array($tx->reference_type, InventoryCostService::costedSources(), true)) {
            return;
        }

        try {
            $this->cost->postForTransaction($tx);
        } catch (\Throwable $e) {
            Log::error('StockDispenseCostObserver: issue not costed', ['transaction_id' => $tx->id, 'error' => $e->getMessage()]);
        }
    }
}
