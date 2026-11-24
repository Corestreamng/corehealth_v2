<?php

use App\Services\Accounting\InventoryCostService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pharmacy returns used to be booked as Dr Inventory at the refund price (or Dr Loss on returns) against the patient's
 * wallet, so the sale was never reversed and the cost of goods never came back. This posts one correction entry per
 * return: the sale is reversed against pharmacy revenue and, for restocked goods, their cost goes back into inventory
 * against cost of sales. Returns approved from now on are booked correctly by the observer.
 *
 * Idempotent (a return with a correction is skipped) and reversible with
 * `php artisan accounting:backfill-inventory-cost --rollback`. Set INVENTORY_BACKFILL_ON_MIGRATE=false to skip it here.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (!filter_var(env('INVENTORY_BACKFILL_ON_MIGRATE', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        foreach (['pharmacy_returns', 'journal_entries', 'accounts', 'accounting_periods'] as $table) {
            if (!Schema::hasTable($table)) {
                return;
            }
        }
        $svc = app(InventoryCostService::class);
        if ($svc->costLooksUnreliable()) {
            Log::warning('Pharmacy return correction skipped: batch cost prices look unreliable. Run `php artisan accounting:backfill-inventory-cost` to preview, then --apply.');

            return;
        }
        $r = $svc->backfillReturns(true);
        Log::info('Pharmacy return correction', ['corrected' => $r['posted'], 'revenue_reversed' => $r['revenue_reversed'], 'cost_restored' => $r['cost_restored'], 'skipped' => $r['skipped']]);
    }

    public function down(): void
    {
        app(InventoryCostService::class)->rollback('Migration rolled back');
    }
};
