<?php

use App\Services\Accounting\InventoryCostService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes the backlog: stock issued to patients was never costed, so profit ignored cost of sales and the inventory
 * asset never went down. This posts the missing cost of goods issued (one entry per month and inventory account, at
 * batch cost) and brings the stock that never reached the ledger onto the books as opening stock.
 *
 * Safe to run twice (every movement carries the entry that costed it), and reversible: php artisan
 * accounting:backfill-inventory-cost --rollback. Preview first with the same command without --apply. Set
 * INVENTORY_BACKFILL_ON_MIGRATE=false to skip it here and run the command yourself.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (!filter_var(env('INVENTORY_BACKFILL_ON_MIGRATE', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        foreach (['stock_batch_transactions', 'stock_batches', 'journal_entries', 'accounts', 'accounting_periods'] as $table) {
            if (!Schema::hasTable($table)) {
                return;
            }
        }

        $svc = app(InventoryCostService::class);
        $backfill = $svc->backfill(true);
        $trueUp = $svc->trueUp(true);
        Log::info('Inventory cost backfill', ['posted_groups' => $backfill['posted'], 'cost' => $backfill['total_cost'], 'movements' => $backfill['moves'], 'skipped' => $backfill['skipped'], 'true_up' => $trueUp['posted'], 'review' => $trueUp['review']]);
    }

    public function down(): void
    {
        app(InventoryCostService::class)->rollback('Migration rolled back');
    }
};
