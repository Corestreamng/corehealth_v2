<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\ProductRequest;
use App\Models\StockBatch;
use App\Models\StockBatchTransaction;
use App\Models\Store;
use App\Services\Accounting\InventoryCostService;
use App\Services\Accounting\ReportService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cost of goods issued: bought stock is an asset, used stock is an expense at batch cost, once.
 */
class InventoryCostTest extends TestCase
{
    private function pharmacyBatch(): StockBatch
    {
        $storeIds = Store::whereIn('distribution_role', [Store::ROLE_PHARMACY_HUB, Store::ROLE_PHARMACY_SATELLITE])->pluck('id');

        return StockBatch::whereIn('store_id', $storeIds)->where('is_active', true)->whereNull('voided_at')->where('current_qty', '>=', 10)->where('cost_price', '>', 0)
            ->whereIn('product_id', \App\Models\Product::where('status', 1)->select('id'))->first() ?? $this->markTestSkipped('No costed pharmacy batch.');
    }

    private function balance(string $code): float
    {
        $id = Account::where('code', $code)->value('id');

        return (float) DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->where('l.account_id', $id)
            ->whereIn('e.status', JournalEntry::LEDGER_STATUSES)->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) AS b')->value('b');
    }

    /** Expense carried by backfill entries that existed before this test started (they are reversed by the rollback too). */
    private function migrationCost(): float
    {
        return $this->preexistingBackfillCost ?? 0.0;
    }

    private ?float $preexistingBackfillCost = null;

    private function dispense(StockBatch $batch, int $qty, string $ref = ProductRequest::class): StockBatchTransaction
    {
        $refId = ProductRequest::value('id') ?? 1;

        return $batch->deductStock($qty, StockBatchTransaction::TYPE_OUT, $ref, $refId, 'Cost test');
    }

    public function test_dispensing_moves_the_cost_from_inventory_to_cost_of_sales_once()
    {
        $batch = $this->pharmacyBatch();
        $cost = round(4 * (float) $batch->cost_price, 2);
        $inv = $this->balance('1300');
        $cos = $this->balance('5010');

        $tx = $this->dispense($batch, 4);
        $tx = $tx instanceof StockBatchTransaction ? $tx : StockBatchTransaction::where('stock_batch_id', $batch->id)->latest('id')->first();

        $this->assertNotNull($tx->fresh()->journal_entry_id, 'The issue carries the entry that costed it.');
        $this->assertEqualsWithDelta($cos + $cost, $this->balance('5010'), 0.01, 'Cost of pharmacy sales grows by the batch cost.');
        $this->assertEqualsWithDelta($inv - $cost, $this->balance('1300'), 0.01, 'Inventory falls by the same amount.');

        $je = JournalEntry::find($tx->fresh()->journal_entry_id);
        $this->assertSame(JournalEntry::STATUS_POSTED, $je->status);
        $this->assertEqualsWithDelta($cost, (float) $je->lines->sum('debit'), 0.01);
        $this->assertEqualsWithDelta($cost, (float) $je->lines->sum('credit'), 0.01);

        // costing the same issue again does nothing
        $this->assertNull(app(InventoryCostService::class)->postForTransaction($tx->fresh()));
        $this->assertEqualsWithDelta($cos + $cost, $this->balance('5010'), 0.01);
    }

    public function test_sources_that_post_their_own_entries_or_cost_nothing_are_not_costed_here()
    {
        $batch = $this->pharmacyBatch();
        $cos = $this->balance('5010') + $this->balance('5020');
        $this->dispense($batch, 1, \App\Models\StockUtilization::class);
        $this->assertEqualsWithDelta($cos, $this->balance('5010') + $this->balance('5020'), 0.01, 'Ward utilisation has its own observer.');

        $donated = StockBatch::where('is_active', true)->where('current_qty', '>=', 5)->where('cost_price', 0)->first();
        if ($donated) {
            $this->dispense($donated, 1);
            $this->assertEqualsWithDelta($cos, $this->balance('5010') + $this->balance('5020'), 0.01, 'A donated, zero-cost batch has nothing to expense.');
        }
    }

    public function test_the_backfill_posts_the_backlog_once_balances_and_can_be_rolled_back()
    {
        $svc = app(InventoryCostService::class);
        $before = app(ReportService::class)->generateTrialBalance(now()->toDateString());

        $this->preexistingBackfillCost = (float) DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->whereIn('e.reference_type', [InventoryCostService::BACKFILL_SOURCE])->where('e.status', JournalEntry::STATUS_POSTED)->sum('l.debit');
        // make a backlog of our own: two issues that were never costed
        $batch = $this->pharmacyBatch();
        $this->dispense($batch, 2);
        $this->dispense($batch->fresh(), 3);
        StockBatchTransaction::where('stock_batch_id', $batch->id)->whereIn('type', ['out'])->where('reference_type', ProductRequest::class)->update(['journal_entry_id' => null]);
        $inv0 = $this->balance('1300') + $this->balance('1310');
        $cost0 = $this->balance('5010') + $this->balance('5020');

        $plan = $svc->backfill(false);
        $this->assertEmpty($plan['skipped']);
        $this->assertGreaterThanOrEqual(2, $plan['moves']);
        $this->assertSame($cost0, $this->balance('5010') + $this->balance('5020'), 'A preview posts nothing.');

        $run = $svc->backfill(true);
        $this->assertSame([], $run['skipped']);
        $this->assertSame(count($plan['groups']), $run['posted']);
        $this->assertEqualsWithDelta($plan['total_cost'], ($this->balance('5010') + $this->balance('5020')) - $cost0, 0.05, 'Expense grows by the planned cost.');
        $this->assertEqualsWithDelta($plan['total_cost'], $inv0 - ($this->balance('1300') + $this->balance('1310')), 0.05, 'Inventory falls by the same.');

        $after = app(ReportService::class)->generateTrialBalance(now()->toDateString());
        $this->assertEqualsWithDelta($before['difference'], $after['difference'], 0.05, 'Balanced entries leave the trial balance difference as it was.');

        $again = $svc->backfill(true);
        $this->assertSame(0, $again['moves'], 'Every movement now carries its entry: a second run has nothing to do.');
        $this->assertSame(0, $again['posted']);

        $rolled = $svc->rollback(); // also undoes what the migration posted on this database, all inside the test transaction
        $this->assertGreaterThanOrEqual($run['posted'], $rolled['reversed']);
        $this->assertEqualsWithDelta(0.0, $this->balance('5010') + $this->balance('5020') - ($cost0 - $this->migrationCost()), 0.05, 'Rolled back: the expense the backfill posted is gone again.');
        $this->assertGreaterThanOrEqual($plan['moves'], $svc->backfill(false)['moves'], 'The movements are free to be costed again.');
    }

    public function test_the_true_up_brings_unrecorded_stock_onto_the_books_once_and_never_forces_a_shortfall()
    {
        $svc = app(InventoryCostService::class);
        $svc->backfill(true);
        $res = $svc->trueUp(true);
        foreach ($res['posted'] as $p) {
            $this->assertGreaterThan(1, $p['amount']);
            $code = $p['code'];
            $this->assertEqualsWithDelta($res['position'][$code]['physical'], $this->balance($code), 1.0, "After the true-up the ledger agrees with the shelves for {$code}.");
        }
        $second = $svc->trueUp(true);
        $this->assertSame([], $second['posted'], 'Once only.');
    }
}
