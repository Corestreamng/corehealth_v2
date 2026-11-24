<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ReportService;
use Tests\TestCase;

/**
 * A posted entry and its reversal cancel out. The reversed original used to drop out of every balance while its
 * reversing entry stayed in, so a reversal subtracted the amount twice.
 */
class ReversalLedgerTest extends TestCase
{
    private function tb(): array
    {
        return collect(app(ReportService::class)->generateTrialBalance(now()->toDateString(), true)['accounts'])
            ->mapWithKeys(fn ($a) => [$a['account_id'] => round($a['debit'] - $a['credit'], 2)])->all();
    }

    public function test_posting_then_reversing_an_entry_leaves_every_account_where_it_was()
    {
        $a = Account::where('is_active', true)->orderBy('id')->first();
        $b = Account::where('is_active', true)->where('id', '!=', $a->id)->orderBy('id')->first();
        $before = $this->tb();

        $svc = app(AccountingService::class);
        $entry = $svc->createAndPostAutomatedEntry('ReversalLedgerTest', random_int(1, 2_000_000_000), 'Reversal test', [
            ['account_id' => $a->id, 'debit_amount' => 750, 'credit_amount' => 0, 'description' => 'Dr'],
            ['account_id' => $b->id, 'debit_amount' => 0, 'credit_amount' => 750, 'description' => 'Cr'],
        ], now()->toDateString(), 1);

        $mid = $this->tb();
        $this->assertEqualsWithDelta(($before[$a->id] ?? 0) + 750, $mid[$a->id] ?? 0, 0.01);

        $svc->reverseEntry($entry, 1, 'test');
        $after = $this->tb();
        foreach ([$a->id, $b->id] as $id) {
            $this->assertEqualsWithDelta($before[$id] ?? 0, $after[$id] ?? 0, 0.01, "Account {$id} is back to where it was after the reversal.");
        }
    }
}
