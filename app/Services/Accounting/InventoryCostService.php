<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\ImmunizationRecord;
use App\Models\MedicationAdministration;
use App\Models\PharmacyReturn;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\StockBatchTransaction;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cost of goods issued (perpetual inventory).
 *
 * Buying stock debits Inventory (an asset, no expense). When stock leaves a store to a patient the cost of what left,
 * at the cost of the batch it came from, moves out of Inventory into an expense:
 *
 *     Dr 5010 Cost of Pharmacy Sales (pharmacy stores) / 5020 Cost of Medical Supplies Used (everywhere else)
 *     Cr 1300 / 1310 / 1320 Inventory
 *
 * so the cost reaches profit and loss once, when it is used, and never when it is bought. Ward utilisation, damages,
 * expiry, adjustments and returns to suppliers already post their own entries and are not touched here.
 *
 * Live: StockDispenseCostObserver calls postForTransaction() for every issue. Backlog: backfill() posts the issues that
 * were never costed (one entry per month and inventory account), trueUp() books the opening stock the ledger never
 * received, and rollback() reverses both. Every transaction carries its journal_entry_id, which is what makes the
 * backfill safe to run twice.
 */
class InventoryCostService
{
    public const BACKFILL_SOURCE = 'InventoryCostBackfill';

    public const TRUEUP_SOURCE = 'InventoryTrueUp';

    public const RETURN_FIX_SOURCE = 'PharmacyReturnCorrection';

    private const EQUITY_CODE = '3100';

    public function __construct(protected AccountingService $accounting)
    {
    }

    /** Stock movements that issue goods to a patient and nothing else costs them. */
    public static function costedSources(): array
    {
        return [ProductRequest::class, ProductOrServiceRequest::class, MedicationAdministration::class, ImmunizationRecord::class];
    }

    public function inventoryCode(?Store $store): string
    {
        if ($store && in_array($store->distribution_role, [Store::ROLE_PHARMACY_HUB, Store::ROLE_PHARMACY_SATELLITE], true)) {
            return '1300';
        }
        if ($store && $store->distribution_role === Store::ROLE_LAB) {
            return '1320';
        }

        return '1310';
    }

    public function costCode(string $inventoryCode): string
    {
        return $inventoryCode === '1300' ? '5010' : '5020';
    }

    /** @return array{0: Account, 1: Account}|null inventory and cost accounts, null when the chart lacks them */
    protected function accountsFor(string $inventoryCode): ?array
    {
        $inv = Account::where('code', $inventoryCode)->first();
        $cost = Account::where('code', $this->costCode($inventoryCode))->first();

        return $inv && $cost ? [$inv, $cost] : null;
    }

    // ═══════════════════════════ Live: one issue ═══════════════════════════

    /** Post the cost of one stock issue. Returns null when nothing is due (no cost, other source, no period, already costed). */
    public function postForTransaction(StockBatchTransaction $tx): ?JournalEntry
    {
        if ($tx->type !== StockBatchTransaction::TYPE_OUT || $tx->journal_entry_id || !in_array($tx->reference_type, self::costedSources(), true)) {
            return null;
        }
        $batch = $tx->stockBatch;
        $cost = round(abs((int) $tx->qty) * (float) ($batch->cost_price ?? 0), 2);
        if (!$batch || $cost <= 0) {
            return null;
        }
        $code = $this->inventoryCode(Store::find($batch->store_id));
        $accounts = $this->accountsFor($code);
        $today = now()->toDateString();
        if (!$accounts || !AccountingPeriod::forDate(Carbon::parse($today))) {
            Log::warning('InventoryCostService: issue not costed (no accounts or no open period); the backfill will pick it up', ['transaction_id' => $tx->id]);

            return null;
        }
        [$inventory, $expense] = $accounts;
        $name = $batch->product?->product_name ?? 'Product #' . $batch->product_id;

        $entry = $this->accounting->createAndPostAutomatedEntry(
            StockBatchTransaction::class,
            $tx->id,
            sprintf('Cost of goods issued: %s × %d (batch %s)', $name, abs((int) $tx->qty), $batch->batch_number ?? $batch->id),
            [
                ['account_id' => $expense->id, 'debit_amount' => $cost, 'credit_amount' => 0, 'description' => "Cost of {$name}", 'product_id' => $batch->product_id],
                ['account_id' => $inventory->id, 'debit_amount' => 0, 'credit_amount' => $cost, 'description' => "Inventory relieved: {$name}", 'product_id' => $batch->product_id],
            ],
            $today,
            auth()->id() ?? $tx->performed_by ?? 1
        );
        $tx->forceFill(['journal_entry_id' => $entry->id])->saveQuietly();

        return $entry;
    }

    /** Everything booked to income accounts (4xxx) so far: the yardstick for whether batch costs look sane. */
    public function incomeBooked(): float
    {
        return (float) DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('e.status', JournalEntry::LEDGER_STATUSES)->where('a.code', 'like', '4%')->selectRaw('COALESCE(SUM(l.credit - l.debit), 0) AS v')->value('v');
    }

    /** True when the backlog costs more than all income ever booked: batch cost prices need checking before it is posted. */
    public function costLooksUnreliable(): bool
    {
        $cost = array_sum(array_column($this->plan(), 'cost'));

        return $cost > 0 && $cost > $this->incomeBooked();
    }

    // ═══════════════════════════ Backlog ═══════════════════════════

    /** Uncosted issues grouped by month and inventory account: what backfill() would post. */
    public function plan(): array
    {
        $rows = DB::table('stock_batch_transactions as t')
            ->join('stock_batches as b', 'b.id', '=', 't.stock_batch_id')
            ->where('t.type', 'out')
            ->whereIn('t.reference_type', self::costedSources())
            ->whereNull('t.journal_entry_id')
            ->where('b.cost_price', '>', 0)
            ->groupBy('b.store_id', DB::raw("DATE_FORMAT(t.created_at, '%Y-%m')"))
            ->selectRaw("b.store_id, DATE_FORMAT(t.created_at, '%Y-%m') AS month, COUNT(*) AS moves, SUM(ABS(t.qty) * b.cost_price) AS cost")
            ->get();

        $stores = Store::whereIn('id', $rows->pluck('store_id')->unique())->get()->keyBy('id');
        $groups = [];
        foreach ($rows as $r) {
            $code = $this->inventoryCode($stores[$r->store_id] ?? null);
            $k = $r->month . '|' . $code;
            $groups[$k] ??= ['month' => $r->month, 'inventory_code' => $code, 'cost_code' => $this->costCode($code), 'moves' => 0, 'cost' => 0.0, 'store_ids' => []];
            $groups[$k]['moves'] += (int) $r->moves;
            $groups[$k]['cost'] = round($groups[$k]['cost'] + (float) $r->cost, 2);
            $groups[$k]['store_ids'][] = (int) $r->store_id;
        }
        ksort($groups);

        return array_values($groups);
    }

    private function baseSourceId(string $month, string $code): int
    {
        return ((int) str_replace('-', '', $month)) * 10 + ['1300' => 1, '1310' => 2, '1320' => 3][$code];
    }

    /** First free source id for a group, so a run after a rollback (reversed entries ignored) still posts. */
    private function sourceIdFor(string $month, string $code): int
    {
        $base = $this->baseSourceId($month, $code);
        $attempt = JournalEntry::where('reference_type', self::BACKFILL_SOURCE)->whereBetween('reference_id', [$base, $base + 99_000_000])->whereRaw('MOD(reference_id, 1000000) = ?', [$base % 1000000])->count();

        return $base + $attempt * 1_000_000;
    }

    /** Date a month's entry carries: its last day when a period is open then, otherwise the first date of the current period. */
    private function entryDateFor(string $month): string
    {
        $end = Carbon::parse($month . '-01')->endOfMonth();
        if (AccountingPeriod::forDate($end)) {
            return $end->toDateString();
        }
        $current = $this->accounting->getCurrentPeriod();

        return optional($current?->start_date)->toDateString() ?? now()->toDateString();
    }

    /**
     * Post the backlog. With $apply false it only reports. Each group is atomic (entry and the marking of its
     * transactions), so an interrupted run resumes where it stopped.
     *
     * @return array{groups: array, total_cost: float, moves: int, posted: int, skipped: array}
     */
    public function backfill(bool $apply = false): array
    {
        $plan = $this->plan();
        $out = ['groups' => $plan, 'total_cost' => round(array_sum(array_column($plan, 'cost')), 2), 'moves' => array_sum(array_column($plan, 'moves')), 'posted' => 0, 'skipped' => []];
        if (!$apply) {
            return $out;
        }

        foreach ($plan as $g) {
            $accounts = $this->accountsFor($g['inventory_code']);
            if (!$accounts) {
                $out['skipped'][] = "{$g['month']} {$g['inventory_code']}: accounts {$g['inventory_code']} / {$g['cost_code']} are not in the chart";

                continue;
            }
            [$inventory, $expense] = $accounts;

            try {
                DB::transaction(function () use ($g, $inventory, $expense, &$out) {
                    $entry = $this->accounting->createAndPostAutomatedEntry(
                        self::BACKFILL_SOURCE,
                        $this->sourceIdFor($g['month'], $g['inventory_code']),
                        sprintf('Cost of goods issued, %s (%s inventory, %s movements): posted from stock records', $g['month'], $g['inventory_code'], number_format($g['moves'])),
                        [
                            ['account_id' => $expense->id, 'debit_amount' => $g['cost'], 'credit_amount' => 0, 'description' => "Cost of goods issued {$g['month']}"],
                            ['account_id' => $inventory->id, 'debit_amount' => 0, 'credit_amount' => $g['cost'], 'description' => "Inventory relieved {$g['month']}"],
                        ],
                        $this->entryDateFor($g['month']),
                        1
                    );
                    $marked = DB::update(
                        "UPDATE stock_batch_transactions t JOIN stock_batches b ON b.id = t.stock_batch_id SET t.journal_entry_id = ?
                         WHERE t.type = 'out' AND t.reference_type IN (" . implode(',', array_fill(0, count(self::costedSources()), '?')) . ")
                         AND t.journal_entry_id IS NULL AND b.cost_price > 0 AND DATE_FORMAT(t.created_at, '%Y-%m') = ?
                         AND b.store_id IN (" . implode(',', array_map('intval', $g['store_ids'])) . ')',
                        array_merge([$entry->id], self::costedSources(), [$g['month']])
                    );
                    if ($marked !== $g['moves']) {
                        throw new \RuntimeException("Marked {$marked} movements but planned {$g['moves']} for {$g['month']}; stock moved while posting. Run again.");
                    }
                    $out['posted']++;
                });
            } catch (\Throwable $e) {
                $out['skipped'][] = "{$g['month']} {$g['inventory_code']}: " . $e->getMessage();
            }
        }

        return $out;
    }

    // ═══════════════════════════ Opening true-up ═══════════════════════════

    /** Inventory per account: what the ledger says, what the batches are worth, and the gap. */
    public function position(): array
    {
        $physical = DB::table('stock_batches')->where('is_active', true)->whereNull('voided_at')->where('current_qty', '>', 0)
            ->groupBy('store_id')->selectRaw('store_id, SUM(current_qty * cost_price) AS v')->get();
        $stores = Store::whereIn('id', $physical->pluck('store_id'))->get()->keyBy('id');
        $byCode = ['1300' => 0.0, '1310' => 0.0, '1320' => 0.0];
        foreach ($physical as $p) {
            $byCode[$this->inventoryCode($stores[$p->store_id] ?? null)] += (float) $p->v;
        }
        $out = [];
        foreach ($byCode as $code => $value) {
            $acc = Account::where('code', $code)->first();
            $gl = $acc ? (float) DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                ->where('l.account_id', $acc->id)->whereIn('e.status', JournalEntry::LEDGER_STATUSES)->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) AS bal')->value('bal') : 0.0;
            $out[$code] = ['physical' => round($value, 2), 'ledger' => round($gl, 2), 'gap' => round($value - $gl, 2)];
        }

        return $out;
    }

    /**
     * Book the stock the ledger never received (legacy imports, transfers from before the books) as opening stock:
     * Dr Inventory / Cr Retained Earnings, up to the physical value. Once only; a ledger that is ahead of the shelves
     * is reported, never forced.
     */
    public function trueUp(bool $apply = false): array
    {
        $pos = $this->position();
        $result = ['position' => $pos, 'posted' => [], 'review' => []];
        $equity = Account::where('code', self::EQUITY_CODE)->first();
        $opening = AccountingPeriod::where('status', 'open')->orderBy('start_date')->first();
        foreach ($pos as $code => $p) {
            if ($p['gap'] < -1) {
                $result['review'][] = "{$code}: the ledger is ahead of physical stock by " . number_format(-$p['gap'], 2) . ': investigate, not adjusted.';
            }
            if ($p['gap'] <= 1 || !$apply) {
                continue;
            }
            if (!$equity || !$opening || JournalEntry::where('reference_type', self::TRUEUP_SOURCE)->where('reference_id', (int) $code)->whereNotIn('status', [JournalEntry::STATUS_REVERSED])->exists()) {
                $result['review'][] = "{$code}: " . (!$equity || !$opening ? 'no retained earnings account or open period' : 'already trued up');

                continue;
            }
            $inv = Account::where('code', $code)->first();
            $entry = $this->accounting->createAndPostAutomatedEntry(
                self::TRUEUP_SOURCE,
                (int) $code,
                sprintf('Opening stock true-up %s: stock on the shelves not yet in the ledger (physical %s, ledger %s)', $code, number_format($p['physical'], 2), number_format($p['ledger'], 2)),
                [
                    ['account_id' => $inv->id, 'debit_amount' => $p['gap'], 'credit_amount' => 0, 'description' => 'Inventory brought onto the books'],
                    ['account_id' => $equity->id, 'debit_amount' => 0, 'credit_amount' => $p['gap'], 'description' => 'Opening stock'],
                ],
                $opening->start_date->toDateString(),
                1
            );
            $result['posted'][] = ['code' => $code, 'amount' => $p['gap'], 'entry_id' => $entry->id];
        }

        return $result;
    }

    // ═══════════════════════════ Pharmacy returns ═══════════════════════════

    /**
     * Returns booked by the old rule (restock: Dr Inventory at the refund price; no restock: Dr Loss on returns; the sale
     * itself never reversed) that have no correction yet.
     */
    protected function returnsToCorrect()
    {
        return PharmacyReturn::query()
            ->whereNotNull('journal_entry_id')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entry_lines as l')->whereColumn('l.journal_entry_id', 'pharmacy_returns.journal_entry_id')
                ->where(fn ($w) => $w->where('l.narration', 'like', 'Restocked return:%')->orWhere('l.narration', 'like', 'Non-restockable return loss:%')))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entries as e')->where('e.reference_type', self::RETURN_FIX_SOURCE)->whereColumn('e.reference_id', 'pharmacy_returns.id')->where('e.status', '!=', JournalEntry::STATUS_REVERSED)->where('e.entry_type', '!=', JournalEntry::TYPE_REVERSAL));
    }

    /** What the correction would book: the sale reversed and, where goods went back on the shelf, their cost restored. */
    public function returnsPlan(): array
    {
        $rows = $this->returnsToCorrect()->with('batch')->get();
        $revenue = 0.0;
        $cost = 0.0;
        foreach ($rows as $r) {
            $revenue += (float) $r->refund_amount;
            $cost += $r->restock ? round((float) $r->qty_returned * (float) ($r->batch->cost_price ?? 0), 2) : 0.0;
        }

        return ['returns' => $rows->count(), 'revenue_reversed' => round($revenue, 2), 'cost_restored' => round($cost, 2)];
    }

    /**
     * One correction entry per return: undo the old debit (inventory at price, or the loss), reverse the sale against
     * pharmacy revenue and, for restocked goods, put the cost back into inventory against cost of sales.
     */
    public function backfillReturns(bool $apply = false): array
    {
        $out = $this->returnsPlan() + ['posted' => 0, 'skipped' => []];
        if (!$apply) {
            return $out;
        }
        $revenue = Account::where('code', '4020')->first();
        $inventory = Account::where('code', '1300')->first();
        $cos = Account::where('code', '5010')->first();
        $loss = Account::where('code', '5060')->first();
        if (!$revenue || !$inventory || !$cos || !$loss) {
            $out['skipped'][] = 'Accounts 4020, 1300, 5010 and 5060 are needed.';

            return $out;
        }
        foreach ($this->returnsToCorrect()->with('batch', 'product')->orderBy('id')->get() as $r) {
            $amount = round((float) $r->refund_amount, 2);
            if ($amount <= 0) {
                continue;
            }
            $cost = $r->restock ? round((float) $r->qty_returned * (float) ($r->batch->cost_price ?? 0), 2) : 0.0;
            $name = $r->product->product_name ?? 'Product #' . $r->product_id;
            $lines = [['account_id' => $revenue->id, 'debit_amount' => $amount, 'credit_amount' => 0, 'description' => "Sale reversed: {$name}"]];
            $lines[] = ['account_id' => ($r->restock ? $inventory : $loss)->id, 'debit_amount' => 0, 'credit_amount' => $amount, 'description' => $r->restock ? 'Undo inventory booked at the refund price' : 'Undo loss booked for the refund'];
            if ($cost > 0) {
                $lines[] = ['account_id' => $inventory->id, 'debit_amount' => $cost, 'credit_amount' => 0, 'description' => "Restocked at cost: {$name}"];
                $lines[] = ['account_id' => $cos->id, 'debit_amount' => 0, 'credit_amount' => $cost, 'description' => "Cost of sale reversed: {$name}"];
            }

            try {
                $this->accounting->createAndPostAutomatedEntry(
                    self::RETURN_FIX_SOURCE,
                    $r->id,
                    "Pharmacy return #{$r->id} corrected: sale reversed" . ($cost > 0 ? ', cost restored' : ''),
                    $lines,
                    $this->entryDateFor(optional($r->approved_at ?? $r->created_at)->format('Y-m') ?? now()->format('Y-m')),
                    1
                );
                $out['posted']++;
            } catch (\Throwable $e) {
                $out['skipped'][] = "Return #{$r->id}: " . $e->getMessage();
            }
        }

        return $out;
    }

    // ═══════════════════════════ Undo ═══════════════════════════

    /** Reverse every backfill and true-up entry and free the transactions they covered. */
    public function rollback(string $reason = 'Inventory cost backfill rolled back'): array
    {
        $reversed = 0;
        foreach (JournalEntry::whereIn('reference_type', [self::BACKFILL_SOURCE, self::TRUEUP_SOURCE, self::RETURN_FIX_SOURCE])->where('status', JournalEntry::STATUS_POSTED)->get() as $entry) {
            DB::transaction(function () use ($entry, $reason, &$reversed) {
                $this->accounting->reverseEntry($entry, 1, $reason);
                DB::update('UPDATE stock_batch_transactions SET journal_entry_id = NULL WHERE journal_entry_id = ?', [$entry->id]);
                $reversed++;
            });
        }

        return ['reversed' => $reversed];
    }
}
