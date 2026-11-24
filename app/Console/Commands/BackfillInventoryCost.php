<?php

namespace App\Console\Commands;

use App\Services\Accounting\InventoryCostService;
use Illuminate\Console\Command;

/**
 * Post the cost of goods issued that was never booked, and bring unrecorded opening stock onto the books.
 * Shows what it would do unless --apply is given. --rollback reverses everything it posted.
 */
class BackfillInventoryCost extends Command
{
    protected $signature = 'accounting:backfill-inventory-cost {--apply : Post the entries (default is a preview)} {--no-true-up : Skip the opening stock true-up} {--rollback : Reverse everything this command posted}';

    protected $description = 'Book cost of goods issued from stock records and the opening stock the ledger never received';

    public function handle(InventoryCostService $svc): int
    {
        if ($this->option('rollback')) {
            $r = $svc->rollback();
            $this->info("Reversed {$r['reversed']} entr" . ($r['reversed'] === 1 ? 'y' : 'ies') . '.');

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $res = $svc->backfill($apply);
        $this->line(($apply ? 'Posted' : 'Would post') . ' cost of goods issued for ' . number_format($res['moves']) . ' stock movements: ' . number_format($res['total_cost'], 2));
        $this->table(['Month', 'Inventory', 'Cost account', 'Movements', 'Cost'], array_map(fn ($g) => [$g['month'], $g['inventory_code'], $g['cost_code'], number_format($g['moves']), number_format($g['cost'], 2)], $res['groups']));
        foreach ($res['skipped'] as $s) {
            $this->warn($s);
        }

        if (!$this->option('no-true-up')) {
            $t = $svc->trueUp($apply);
            $this->line('Inventory: ledger against the shelves' . ($apply ? ' (after posting)' : ' (before posting; the backlog above lowers the ledger further)'));
            $this->table(['Account', 'Ledger', 'Physical stock', 'Gap'], array_map(fn ($c, $p) => [$c, number_format($p['ledger'], 2), number_format($p['physical'], 2), number_format($p['gap'], 2)], array_keys($t['position']), $t['position']));
            foreach ($t['posted'] as $p) {
                $this->info("Opening stock true-up {$p['code']}: " . number_format($p['amount'], 2) . " (entry #{$p['entry_id']})");
            }
            foreach ($t['review'] as $r) {
                $this->warn($r);
            }
        }

        return $res['skipped'] ? self::FAILURE : self::SUCCESS;
    }
}
