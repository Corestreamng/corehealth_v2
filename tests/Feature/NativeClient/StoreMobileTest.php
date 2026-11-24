<?php

namespace Tests\Feature\NativeClient;

use App\Models\Accounting\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use App\Models\Store;
use App\Models\StoreRequisition;
use App\Models\Supplier;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff app, Store and Staff Supplies. Every route is the web controller's own (guards, gates and observers
 * included); these tests walk the real flows and check the ledger and the tally card agree.
 */
class StoreMobileTest extends TestCase
{
    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    private function as(string $role): User
    {
        $u = User::factory()->create(['status' => 1]);
        $u->assignRole(Role::where('name', $role)->exists() ? $role : 'ADMIN');
        Sanctum::actingAs($u);

        return $u;
    }

    private function stockedBatch(int $storeId): StockBatch
    {
        return StockBatch::where('store_id', $storeId)->whereIn('product_id', Product::where('status', 1)->select('id'))->where('current_qty', '>=', 10)->where('is_active', true)
            ->whereNull('voided_at')->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now()))
            ->first() ?? $this->markTestSkipped("No stocked batch in store {$storeId}.");
    }

    public function test_guards_follow_the_sidebar()
    {
        $this->as('NURSE');
        $this->getJson('/api/mobile/store/context', self::AJAX)->assertStatus(403);
        $this->getJson('/api/mobile/stock/context', self::AJAX)->assertOk();
        $this->getJson('/api/mobile/stock/requisitions?draw=1&start=0&length=2', self::AJAX)->assertOk();

        $this->as('STORE');
        $this->getJson('/api/mobile/store/context', self::AJAX)->assertOk()->assertJsonStructure(['stores', 'can' => ['store_admin']]);
        $this->getJson('/api/mobile/store/dashboard', self::AJAX)->assertOk()->assertJsonStructure(['stats', 'low_stock', 'expiring']);
        $this->assertTrue($this->getJson('/api/mobile/store/context', self::AJAX)->json('can.store_admin'));

        $this->as('LAB SCIENTIST');
        $this->getJson('/api/mobile/store/dashboard', self::AJAX)->assertStatus(403);
    }

    public function test_the_read_endpoints_answer_json()
    {
        $this->as('SUPERADMIN');
        $store = Store::where('distribution_role', 'pharmacy_hub')->value('id') ?? Store::value('id');
        foreach ([
            "stock-overview?store_id={$store}&draw=1&start=0&length=2",
            "reports/expiry?store_id={$store}&per_page=3",
            "reports/stock-value?store_id={$store}&per_page=3",
            "tally-card/page?axis=store&store_id={$store}&per_page=3",
            "tally-card/pending-actions?store_id={$store}",
            'purchase-orders?draw=1&start=0&length=2',
            'damages?draw=1&start=0&length=2',
            'requisition-returns?draw=1&start=0&length=2',
            'po-returns?draw=1&start=0&length=2',
            'search-products?term=a',
        ] as $path) {
            $r = $this->getJson("/api/mobile/store/{$path}", self::AJAX);
            $this->assertSame(200, $r->status(), "{$path} -> {$r->status()}");
            $this->assertStringContainsString('json', (string) $r->headers->get('content-type'));
        }
    }

    public function test_a_requisition_moves_stock_and_both_tally_cards_agree()
    {
        $this->as('SUPERADMIN');
        $from = Store::where('distribution_role', 'pharmacy_hub')->value('id') ?? $this->markTestSkipped('No pharmacy hub.');
        $to = Store::where('distribution_role', 'ward')->where('status', true)->value('id') ?? $this->markTestSkipped('No ward store.');
        $batch = $this->stockedBatch($from);
        $pid = $batch->product_id;

        $created = $this->postJson('/api/mobile/stock/requisitions', [
            'from_store_id' => $from, 'to_store_id' => $to, 'request_notes' => 'Native test',
            'items' => [['product_id' => $pid, 'requested_qty' => 3]],
        ], self::AJAX);
        if ($created->status() === 403) {
            $this->markTestSkipped('Lane policy blocks this pair here: ' . $created->json('message'));
        }
        $created->assertOk();
        $req = StoreRequisition::where('request_notes', 'Native test')->latest('id')->firstOrFail();

        $detail = $this->getJson("/api/mobile/stock/requisitions/{$req->id}", self::AJAX)->assertOk()->json();
        $this->assertTrue($detail['can']['approve'], 'An admin may approve.');
        $this->postJson("/api/mobile/stock/requisitions/{$req->id}/approve", [], self::AJAX)->assertOk();

        $item = $req->items()->first();
        $before = (int) \App\Models\StoreStock::where('store_id', $to)->where('product_id', $pid)->value('current_quantity');
        $this->postJson("/api/mobile/stock/requisitions/{$req->id}/fulfill", [
            'items' => [['requisition_item_id' => $item->id, 'product_id' => $pid, 'batches' => [$batch->id => 3]]],
        ], self::AJAX)->assertOk();

        $this->assertSame($before + 3, (int) \App\Models\StoreStock::where('store_id', $to)->where('product_id', $pid)->value('current_quantity'));
        $out = $this->getJson("/api/mobile/store/tally-card/page?axis=product&store_id={$from}&product_id={$pid}&type=out&per_page=5", self::AJAX)->json();
        $in = $this->getJson("/api/mobile/store/tally-card/page?axis=product&store_id={$to}&product_id={$pid}&type=in&per_page=100", self::AJAX)->json();
        $this->assertContains('transfer_out', array_column($out['transactions'], 'raw_type'));
        $this->assertContains('transfer_in', array_column($in['transactions'], 'direction'));
    }

    public function test_a_purchase_order_posts_payable_then_payment_clears_it()
    {
        $this->as('SUPERADMIN');
        $store = Store::where('distribution_role', 'central')->value('id') ?? $this->markTestSkipped('No central store.');
        $supplier = Supplier::value('id') ?? $this->markTestSkipped('No supplier.');
        $product = Product::where('status', 1)->first();

        $this->postJson('/api/mobile/store/purchase-orders', [
            'supplier_id' => $supplier, 'target_store_id' => $store, 'notes' => 'Native PO test',
            'items' => [['product_id' => $product->id, 'ordered_qty' => 10, 'unit_cost' => 250]], 'action' => 'save',
        ], self::AJAX)->assertSuccessful();
        $po = PurchaseOrder::where('notes', 'Native PO test')->latest('id')->firstOrFail();

        $this->postJson("/api/mobile/store/purchase-orders/{$po->id}/submit", [], self::AJAX)->assertOk();
        $this->postJson("/api/mobile/store/purchase-orders/{$po->id}/approve", [], self::AJAX)->assertOk();
        $this->assertTrue($this->getJson("/api/mobile/store/purchase-orders/{$po->id}", self::AJAX)->json('can.receive'));

        $jeBefore = JournalEntry::count();
        $item = $po->items()->first();
        $this->postJson("/api/mobile/store/purchase-orders/{$po->id}/receive", [
            'items' => [['item_id' => $item->id, 'qty' => 10, 'batch_number' => 'NATIVE-' . uniqid(), 'actual_cost' => 250]],
        ], self::AJAX)->assertOk();
        $this->assertGreaterThan($jeBefore, JournalEntry::count(), 'Receiving posts Dr Inventory / Cr Accounts Payable.');

        $detail = $this->getJson("/api/mobile/store/purchase-orders/{$po->id}", self::AJAX)->json();
        $this->assertEqualsWithDelta(2500.0, $detail['totals']['balance_due'], 0.01);
        $this->assertTrue($detail['can']['record_payment']);

        $jeBefore = JournalEntry::count();
        $this->postJson("/api/mobile/store/purchase-orders/{$po->id}/payment", [
            'payment_date' => now()->toDateString(), 'amount' => 2500, 'payment_method' => 'cash',
        ], self::AJAX)->assertOk();
        $this->assertGreaterThan($jeBefore, JournalEntry::count(), 'Paying posts Dr Accounts Payable / Cr Cash.');
        $this->assertEqualsWithDelta(0.0, $this->getJson("/api/mobile/store/purchase-orders/{$po->id}", self::AJAX)->json('totals.balance_due'), 0.01);
    }

    public function test_adjust_and_void_adjustment_keep_the_tally_and_ledger_in_step()
    {
        $this->as('SUPERADMIN');
        $store = Store::where('distribution_role', 'pharmacy_hub')->value('id') ?? $this->markTestSkipped('No pharmacy hub.');
        $batch = $this->stockedBatch($store);
        $qty = (int) $batch->current_qty;

        $this->postJson("/api/mobile/store/batch/{$batch->id}/adjust", ['adjustment_type' => 'subtract', 'qty' => 2, 'reason' => 'Native count correction'], self::AJAX)->assertOk();
        $this->assertSame($qty - 2, (int) $batch->fresh()->current_qty);
        $adj = $this->getJson("/api/mobile/store/tally-card/page?axis=product&store_id={$store}&product_id={$batch->product_id}&type=adjustment&per_page=100", self::AJAX)->json('transactions');
        $row = collect($adj)->last();
        $this->assertSame(2, $row['out_qty']);
        $this->assertNotNull($row['journal_entry_id'], 'The adjustment posted a journal.');

        $this->postJson("/api/mobile/store/adjustment/{$row['id']}/void", ['reason' => 'Recorded in error'], self::AJAX)->assertOk();
        $this->assertSame($qty, (int) $batch->fresh()->current_qty);
    }

    public function test_pending_actions_can_be_counted_or_paged_without_loading_every_row()
    {
        $this->as('SUPERADMIN');
        $store = Store::where('distribution_role', 'pharmacy_hub')->value('id') ?? Store::value('id');
        $counts = $this->getJson("/api/mobile/store/tally-card/pending-actions?store_id={$store}&counts_only=1", self::AJAX)->assertOk()->json();
        $this->assertSame(['incoming', 'outgoing', 'pos', 'damages', 'req_returns', 'po_returns'], array_keys($counts['counts']));
        $this->assertArrayNotHasKey('incoming', $counts, 'Counts only: no rows are loaded.');

        $page = $this->getJson("/api/mobile/store/tally-card/pending-actions?store_id={$store}&queue=outgoing&per_page=3", self::AJAX)->assertOk()->json();
        $this->assertLessThanOrEqual(3, count($page['data']));
        $this->assertSame($counts['counts']['outgoing'], $page['total'], 'The page total is the same figure as the count.');
        $this->getJson("/api/mobile/store/tally-card/pending-actions?store_id={$store}&queue=nonsense", self::AJAX)->assertStatus(422);
        $this->assertArrayHasKey('incoming', $this->getJson("/api/mobile/store/tally-card/pending-actions?store_id={$store}", self::AJAX)->json(), 'The web call is unchanged.');
    }
}
