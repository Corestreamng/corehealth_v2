<?php

namespace Tests\Feature\NativeClient;

use App\Models\Store;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The staff app asks for a list the DataTables way: `search[value]` (dropped when empty, leaving only `search[regex]`),
 * `order`, `columns`. Several controllers read `search` as plain text and failed with "Array to string conversion",
 * which the app shows as "something went wrong" after the loading shimmer.
 */
class DataTablesRequestShapeTest extends TestCase
{
    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    /** What the app sends for a list with nothing typed, with a term typed, and sorted. */
    private function shapes(): array
    {
        $base = ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['regex' => 'false'], 'columns' => [['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['regex' => 'false']]]];

        return [
            'nothing typed' => $base,
            'a term typed' => array_replace_recursive($base, ['search' => ['value' => 'a']]),
            'sorted' => array_replace_recursive($base, ['order' => [['column' => 0, 'dir' => 'desc']]]),
        ];
    }

    public function test_the_lists_the_app_pages_accept_the_datatables_request_shape()
    {
        $u = User::factory()->create(['status' => 1]);
        $u->assignRole('SUPERADMIN');
        Sanctum::actingAs($u);
        $store = Store::value('id');
        $paths = [
            "store/stock-overview?store_id={$store}", 'surgery/queue', 'pharmacy/stock/overview', 'pharmacy/reports/stock', 'pharmacy/reports/dispensing',
            'accounting/chart-of-accounts', 'hmo/reports/claims', 'hmo/reports/auth-codes', 'hmo/reports/remittances', 'hmo/hmos', 'hmo/tariffs', 'hmo/organizations', 'hmo/patients',
            'store/purchase-orders', 'store/damages', 'store/po-returns', 'store/requisition-returns', 'stock/requisitions',
        ];
        foreach ($paths as $path) {
            foreach ($this->shapes() as $label => $params) {
                $sep = str_contains($path, '?') ? '&' : '?';
                $r = $this->getJson("/api/mobile/{$path}{$sep}" . http_build_query($params), self::AJAX);
                $this->assertLessThan(400, $r->status(), "{$path} ({$label}): " . substr($r->getContent(), 0, 200));
            }
        }
    }

    public function test_the_search_text_helper_reads_both_forms()
    {
        $plain = \Illuminate\Http\Request::create('/x', 'GET', ['search' => ' term ']);
        $nested = \Illuminate\Http\Request::create('/x', 'GET', ['search' => ['value' => 'term', 'regex' => 'false']]);
        $none = \Illuminate\Http\Request::create('/x', 'GET', ['search' => ['regex' => 'false']]);
        $this->assertSame('term', $plain->searchText());
        $this->assertSame('term', $nested->searchText());
        $this->assertSame('', $none->searchText());
    }

    public function test_the_claims_report_searches_in_the_database_and_pages_it()
    {
        $u = User::factory()->create(['status' => 1]);
        $u->assignRole('SUPERADMIN');
        Sanctum::actingAs($u);
        $all = $this->getJson('/api/mobile/hmo/reports/claims?draw=1&start=0&length=3', self::AJAX)->assertOk()->json();
        $this->assertLessThanOrEqual(3, count($all['data']), 'only the requested page is built');
        $none = $this->getJson('/api/mobile/hmo/reports/claims?draw=1&start=0&length=3&search[value]=zzz-no-such-claim', self::AJAX)->assertOk()->json();
        $this->assertSame(0, (int) $none['recordsFiltered']);
        $this->assertSame((int) $all['recordsTotal'], (int) $none['recordsTotal']);
    }
}
