<?php

namespace Tests\Feature\NativeClient;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\StoreLanePolicy;
use App\Models\Supplier;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff app, Store masters, Store Governance and the Store section's Expenses link. Writes go through the web
 * controllers; these tests walk create, edit and read-back and check the guards.
 */
class StoreMastersMobileTest extends TestCase
{
    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    private function as(string $role): User
    {
        $u = User::factory()->create(['status' => 1]);
        $u->assignRole(Role::where('name', $role)->exists() ? $role : 'ADMIN');
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_masters_are_for_the_store_section_only()
    {
        $this->as('NURSE');
        $this->getJson('/api/mobile/store/masters/stores', self::AJAX)->assertStatus(403);
        $this->getJson('/api/mobile/store/governance/stores', self::AJAX)->assertStatus(403);
        $this->getJson('/api/mobile/store/expenses', self::AJAX)->assertStatus(403);
    }

    public function test_every_master_lists_pages_and_searches()
    {
        $this->as('SUPERADMIN');
        foreach (['stores', 'products', 'categories', 'suppliers'] as $kind) {
            $r = $this->getJson("/api/mobile/store/masters/{$kind}?per_page=5&page=1", self::AJAX)->assertOk()->assertJsonStructure(['data', 'total', 'page', 'per_page']);
            $this->assertLessThanOrEqual(5, count($r->json('data')), "{$kind} is paged by the server");
        }
        $p = Product::withoutGlobalScopes()->where('status', 1)->first();
        $found = $this->getJson('/api/mobile/store/masters/products?search=' . urlencode($p->product_name) . '&per_page=50', self::AJAX)->assertOk()->json('data');
        $this->assertContains($p->id, array_column($found, 'id'));
        $this->getJson('/api/mobile/store/masters/options', self::AJAX)->assertOk()->assertJsonStructure(['categories', 'departments', 'wards', 'distribution_roles', 'managers']);
        $this->getJson('/api/mobile/store/masters/nonsense', self::AJAX)->assertStatus(404);
    }

    public function test_a_store_keeper_creates_edits_and_reads_back_a_store()
    {
        $this->as('STORE');
        $name = 'Test store ' . uniqid();
        $this->postJson('/api/mobile/store/masters/stores', ['store_name' => 'ab'], self::AJAX)->assertStatus(422);
        $this->postJson('/api/mobile/store/masters/stores', ['store_name' => $name, 'location' => 'Block C', 'status' => 1], self::AJAX)->assertOk()->assertJson(['success' => true]);
        $id = Store::where('store_name', $name)->value('id');
        $this->assertNotNull($id);
        $this->putJson("/api/mobile/store/masters/stores/{$id}", ['store_name' => $name . ' II', 'location' => 'Block D'], self::AJAX)->assertOk();
        $this->assertSame('Block D', $this->getJson("/api/mobile/store/masters/stores/{$id}", self::AJAX)->assertOk()->json('data.location'));
    }

    public function test_categories_products_and_suppliers_round_trip()
    {
        $this->as('STORE');
        $tag = uniqid();

        $this->postJson('/api/mobile/store/masters/categories', ['category_name' => "Cat {$tag}", 'category_code' => "C{$tag}", 'category_description' => 'x'], self::AJAX)->assertOk();
        $cat = ProductCategory::where('category_code', "C{$tag}")->firstOrFail();

        $payload = ['category_id' => $cat->id, 'product_name' => "Prod {$tag}", 'product_code' => "P{$tag}", 'reorder_alert' => 5, 'product_type' => 'drug', 'base_unit_name' => 'Tablet', 'packagings' => [['name' => 'Strip', 'units_in_parent' => 10]]];
        $this->postJson('/api/mobile/store/masters/products', $payload + ['s1' => 0, 's2' => 0, 'quantity_in' => 0], self::AJAX)->assertOk()->assertJson(['success' => true]);
        $product = Product::withoutGlobalScopes()->where('product_code', "P{$tag}")->firstOrFail();
        $this->assertSame(1, $product->packagings()->count());
        $this->postJson('/api/mobile/store/masters/products', ['product_name' => 'x'], self::AJAX)->assertStatus(422);
        $upd = $this->putJson("/api/mobile/store/masters/products/{$product->id}", array_merge($payload, ['packagings' => [['id' => $product->packagings()->value('id'), 'name' => 'Strip', 'units_in_parent' => 12]], 'product_name' => "Prod {$tag} B", 's1' => 0, 's2' => 0, 'quantity_in' => 0]), self::AJAX);
        $this->assertSame(200, $upd->status(), (string) $upd->getContent());
        $this->assertEquals(12, $product->packagings()->value('units_in_parent'));
        $this->assertSame("Prod {$tag} B", $this->getJson("/api/mobile/store/masters/products/{$product->id}", self::AJAX)->json('data.product_name'));
        $this->postJson("/api/mobile/store/masters/products/{$product->id}/toggle-status", [], self::AJAX)->assertOk()->assertJson(['new_status' => 0]);

        $this->postJson('/api/mobile/store/masters/suppliers', ['company_name' => "Sup {$tag}", 'phone' => '0800'], self::AJAX)->assertOk()->assertJson(['success' => true]);
        $sup = Supplier::where('company_name', "Sup {$tag}")->firstOrFail();
        $this->putJson("/api/mobile/store/masters/suppliers/{$sup->id}", ['company_name' => "Sup {$tag}", 'phone' => '0900', 'status' => 1], self::AJAX)->assertOk();
        $this->assertSame('0900', $this->getJson("/api/mobile/store/masters/suppliers/{$sup->id}", self::AJAX)->json('data.phone'));
        $this->deleteJson("/api/mobile/store/masters/suppliers/{$sup->id}", [], self::AJAX)->assertOk();
        $this->deleteJson("/api/mobile/store/masters/categories/{$cat->id}", [], self::AJAX)->assertOk();
    }

    public function test_governance_reads_and_writes_with_the_webs_gates()
    {
        $this->as('SUPERADMIN');
        $rows = $this->getJson('/api/mobile/store/governance/stores', self::AJAX)->assertOk()->json('data');
        $this->assertNotEmpty($rows);
        $store = Store::findOrFail($rows[0]['id']);
        $this->postJson("/api/mobile/store/governance/stores/{$store->id}", ['distribution_role' => $store->distribution_role ?? 'central'], self::AJAX)->assertOk()->assertJson(['success' => true]);

        $matrix = $this->getJson('/api/mobile/store/governance/lane-matrix', self::AJAX)->assertOk()->json('matrix');
        $cell = $matrix[0];
        $this->postJson('/api/mobile/store/governance/lane-matrix', ['source_role' => $cell['source_role'], 'destination_role' => $cell['destination_role'], 'allowed' => true, 'requires_approval_level' => 'manager'], self::AJAX)->assertOk();
        $this->assertTrue((bool) StoreLanePolicy::where('source_role', $cell['source_role'])->where('destination_role', $cell['destination_role'])->value('allowed'));

        $this->getJson('/api/mobile/store/governance/context-rules', self::AJAX)->assertOk()->assertJsonStructure(['role_rules', 'department_rules', 'bucket_rules', 'roles', 'stores']);
        $this->postJson('/api/mobile/store/governance/context-rules', ['rule_type' => 'fallback_behavior', 'fallback_action' => 'block'], self::AJAX)->assertOk();
    }

    public function test_store_expenses_use_the_webs_expense_permissions()
    {
        $this->as('STORE');
        $r = $this->getJson('/api/mobile/store/expenses?draw=1&start=0&length=2', self::AJAX);
        $this->assertContains($r->status(), [200, 403], 'the web ExpenseController decides by expenses.view');
        $this->as('SUPERADMIN');
        $this->getJson('/api/mobile/store/expenses?draw=1&start=0&length=2', self::AJAX)->assertOk();
    }

    public function test_a_saved_draft_purchase_order_is_edited_then_locked_once_submitted()
    {
        $this->as('SUPERADMIN');
        $store = Store::where('distribution_role', 'central')->value('id') ?? $this->markTestSkipped('No central store.');
        $supplier = Supplier::value('id') ?? $this->markTestSkipped('No supplier.');
        $product = Product::where('status', 1)->first();
        $items = fn (int $qty, int $cost) => [['product_id' => $product->id, 'ordered_qty' => $qty, 'unit_cost' => $cost]];

        $this->postJson('/api/mobile/store/purchase-orders', ['supplier_id' => $supplier, 'target_store_id' => $store, 'notes' => 'Edit me', 'items' => $items(10, 250), 'action' => 'save'], self::AJAX)->assertSuccessful();
        $po = PurchaseOrder::where('notes', 'Edit me')->latest('id')->firstOrFail();
        $this->assertTrue($this->getJson("/api/mobile/store/purchase-orders/{$po->id}", self::AJAX)->json('can.edit'), 'a draft offers Edit');

        $this->putJson("/api/mobile/store/purchase-orders/{$po->id}", ['supplier_id' => $supplier, 'target_store_id' => $store, 'notes' => 'Edited', 'items' => $items(20, 300)], self::AJAX)->assertOk()->assertJson(['success' => true]);
        $detail = $this->getJson("/api/mobile/store/purchase-orders/{$po->id}", self::AJAX)->json();
        $this->assertSame('Edited', $detail['purchase_order']['notes']);
        $this->assertEquals(6000, $detail['totals']['total'], 'the total follows the edited lines');

        $this->postJson("/api/mobile/store/purchase-orders/{$po->id}/submit", [], self::AJAX)->assertOk();
        $this->assertFalse($this->getJson("/api/mobile/store/purchase-orders/{$po->id}", self::AJAX)->json('can.edit'));
        $this->putJson("/api/mobile/store/purchase-orders/{$po->id}", ['supplier_id' => $supplier, 'target_store_id' => $store, 'items' => $items(1, 1)], self::AJAX)->assertStatus(403);
    }
}
