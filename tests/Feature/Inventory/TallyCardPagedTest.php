<?php

namespace Tests\Feature\Inventory;

use App\Models\StockBatchTransaction;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The paged tally feed (used by the staff app) must say exactly what the web feed says: same rows, same running
 * balances, same totals, with the opening balance and totals computed in SQL.
 */
class TallyCardPagedTest extends TestCase
{
    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

    private function admin(): void
    {
        $u = User::factory()->create(['status' => 1]);
        $u->assignRole(Role::where('name', 'SUPERADMIN')->exists() ? 'SUPERADMIN' : 'ADMIN');
        $this->actingAs($u);
    }

    private function busy(): array
    {
        $r = StockBatchTransaction::join('stock_batches as b', 'b.id', '=', 'stock_batch_transactions.stock_batch_id')
            ->selectRaw('b.store_id, b.product_id, COUNT(*) c')->groupBy('b.store_id', 'b.product_id')->orderByDesc('c')->first();

        return $r ? [$r->store_id, $r->product_id] : $this->markTestSkipped('No stock transactions.');
    }

    public function test_paged_rows_and_balances_equal_the_web_feed_for_a_product()
    {
        $this->admin();
        [$store, $product] = $this->busy();
        $q = ['axis' => 'product', 'store_id' => $store, 'product_id' => $product];
        $web = $this->getJson('/inventory/store-workbench/tally-card/data?' . http_build_query($q), self::AJAX)->assertOk()->json();

        $collected = [];
        $page = 1;
        do {
            $p = $this->getJson('/inventory/store-workbench/tally-card/page?' . http_build_query($q + ['page' => $page, 'per_page' => 7]), self::AJAX)->assertOk()->json();
            $collected = array_merge($collected, $p['transactions']);
            $page++;
        } while ($page <= $p['pagination']['last_page'] && $page < 60);

        $this->assertSame(count($web['transactions']), $p['pagination']['total']);
        foreach (array_slice($web['transactions'], 0, count($collected)) as $i => $row) {
            $this->assertSame($row['id'], $collected[$i]['id']);
            $this->assertSame($row['bal_after'], $collected[$i]['bal_after'], "balance after row {$row['id']}");
            $this->assertSame($row['in_qty'], $collected[$i]['in_qty']);
            $this->assertSame($row['out_qty'], $collected[$i]['out_qty']);
        }
        $this->assertSame($web['summary']['total_in'], $p['summary']['total_in']);
        $this->assertSame($web['summary']['total_out'], $p['summary']['total_out']);
    }

    public function test_a_date_range_gets_the_same_opening_and_closing_and_pages_stay_correct()
    {
        $this->admin();
        [$store, $product] = $this->busy();
        $from = now()->subMonths(3)->toDateString();
        $q = ['axis' => 'product', 'store_id' => $store, 'product_id' => $product, 'date_from' => $from];
        $web = $this->getJson('/inventory/store-workbench/tally-card/data?' . http_build_query($q), self::AJAX)->json();
        $p = $this->getJson('/inventory/store-workbench/tally-card/page?' . http_build_query($q + ['per_page' => 5, 'page' => 2]), self::AJAX)->assertOk()->json();

        $this->assertSame($web['summary']['opening_balance'], $p['summary']['opening_balance']);
        $this->assertSame($web['summary']['closing_balance'], $p['summary']['closing_balance']);
        $this->assertSame($p['summary']['opening_balance'] + $p['summary']['net_movement'], $p['summary']['closing_balance']);
        $this->assertLessThanOrEqual(5, count($p['transactions']));
    }

    public function test_the_whole_store_axis_pages_and_filters_by_type()
    {
        $this->admin();
        [$store] = $this->busy();
        $q = ['axis' => 'store', 'store_id' => $store, 'per_page' => 10];
        $p = $this->getJson('/inventory/store-workbench/tally-card/page?' . http_build_query($q), self::AJAX)->assertOk()->json();
        $this->assertLessThanOrEqual(10, count($p['transactions']));
        $this->assertGreaterThan(10, $p['pagination']['total'], 'The store ledger is far larger than a page.');

        $in = $this->getJson('/inventory/store-workbench/tally-card/page?' . http_build_query($q + ['type' => 'in']), self::AJAX)->json();
        foreach ($in['transactions'] as $row) {
            $this->assertContains($row['raw_type'], ['in', 'transfer_in', 'return', 'req_return']);
        }
    }

    public function test_a_store_the_user_may_not_use_is_refused_and_non_store_staff_are_blocked()
    {
        $nurse = User::factory()->create(['status' => 1]);
        $nurse->assignRole('NURSE');
        $this->actingAs($nurse);
        [$store, $product] = $this->busy();
        $this->getJson('/inventory/store-workbench/tally-card/page?axis=product&store_id=' . $store . '&product_id=' . $product, self::AJAX)->assertStatus(403);
    }
}
