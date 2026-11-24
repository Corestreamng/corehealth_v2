<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\StoreContextRule;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Store masters (Stores, Products, Categories, Suppliers) and Store Governance for the staff app.
 *
 * Reads only: the web lists are DataTables with HTML columns and its edit forms are Blade pages, so this returns the
 * same records as plain JSON, paged by the server. Every write goes to the web controller (StoreController,
 * ProductController, ProductCategoryController, SupplierController, StoreGovernanceController), so validation and
 * side effects stay in one place.
 */
class MobileStoreMastersController extends Controller
{
    private const KINDS = ['stores', 'products', 'categories', 'suppliers'];

    private function paged($query, Request $request, callable $map): array
    {
        $per = max(1, min(100, (int) $request->input('per_page', 25)));
        $page = max(1, (int) $request->input('page', 1));
        $total = (clone $query)->count();
        $rows = $query->forPage($page, $per)->get()->map($map)->values();

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per];
    }

    private function like($query, ?string $term, array $cols): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }
        $query->where(function ($q) use ($term, $cols) {
            foreach ($cols as $c) {
                $q->orWhere($c, 'LIKE', '%' . addcslashes($term, '%_\\') . '%');
            }
        });
    }

    public function index(Request $request, string $kind)
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        $search = $request->input('search');

        if ($kind === 'stores') {
            $q = Store::with(['ward', 'department', 'manager', 'parentStore'])->orderBy('store_name');
            $this->like($q, $search, ['store_name', 'location']);
            if ($request->filled('status')) {
                $q->where('status', (int) $request->input('status'));
            }

            return response()->json($this->paged($q, $request, fn ($s) => [
                'id' => $s->id, 'store_name' => $s->store_name, 'location' => $s->location, 'status' => (int) $s->status,
                'distribution_role' => $s->distribution_role, 'ward' => $s->ward?->name, 'department' => $s->department?->name,
            ]));
        }

        if ($kind === 'categories') {
            $q = ProductCategory::with('department')->orderBy('category_name');
            $this->like($q, $search, ['category_name', 'category_code']);

            return response()->json($this->paged($q, $request, fn ($c) => [
                'id' => $c->id, 'category_name' => $c->category_name, 'category_code' => $c->category_code,
                'category_description' => $c->category_description, 'department_id' => $c->department_id, 'department' => $c->department?->name,
                'status' => (int) $c->status,
            ]));
        }

        if ($kind === 'suppliers') {
            $q = Supplier::withCount(['stockBatches', 'purchaseOrders'])->orderBy('company_name');
            $this->like($q, $search, ['company_name', 'contact_person', 'phone', 'email']);
            if ($request->filled('status')) {
                $q->where('status', (int) $request->input('status'));
            }

            return response()->json($this->paged($q, $request, fn ($s) => [
                'id' => $s->id, 'company_name' => $s->company_name, 'contact_person' => $s->contact_person, 'phone' => $s->phone,
                'email' => $s->email, 'status' => (bool) $s->status, 'batches' => (int) $s->stock_batches_count,
                'purchase_orders' => (int) $s->purchase_orders_count, 'outstanding_balance' => (float) $s->outstanding_balance,
            ]));
        }

        $q = Product::withoutGlobalScopes()->with(['category', 'price'])->withSum(['stockBatches as stock_qty' => fn ($b) => $b->where('is_active', true)->whereNull('voided_at')], 'current_qty')->orderBy('product_name');
        $this->like($q, $search, ['product_name', 'product_code']);
        if (!filter_var($request->input('show_deactivated'), FILTER_VALIDATE_BOOLEAN)) {
            $q->where('status', 1);
        }
        if ($request->filled('product_type') && $request->input('product_type') !== 'all') {
            $q->where('product_type', $request->input('product_type'));
        }
        if ($request->filled('category_id') && $request->input('category_id') !== 'all') {
            $q->where('category_id', (int) $request->input('category_id'));
        }

        return response()->json($this->paged($q, $request, fn ($p) => [
            'id' => $p->id, 'product_name' => $p->product_name, 'product_code' => $p->product_code, 'product_type' => $p->product_type,
            'category_id' => $p->category_id, 'category' => $p->category?->category_name, 'base_unit_name' => $p->base_unit_name,
            'reorder_alert' => $p->reorder_alert, 'status' => (int) $p->status, 'stock_qty' => (float) ($p->stock_qty ?? 0),
            'sale_price' => $p->price?->current_sale_price !== null ? (float) $p->price->current_sale_price : null,
        ]));
    }

    public function show(string $kind, int $id)
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        $row = match ($kind) {
            'stores' => Store::findOrFail($id),
            'categories' => ProductCategory::findOrFail($id),
            'suppliers' => Supplier::findOrFail($id),
            default => Product::withoutGlobalScopes()->with(['price', 'packagings' => fn ($q) => $q->orderBy('level')])->findOrFail($id),
        };

        return response()->json(['data' => $row]);
    }

    /** Lookups the master forms and the governance screen need. */
    public function options()
    {
        return response()->json([
            'categories' => ProductCategory::where('status', 1)->orderBy('category_name')->get(['id', 'category_name', 'department_id']),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'wards' => Ward::orderBy('name')->get(['id', 'name']),
            'stores' => Store::orderBy('store_name')->get(['id', 'store_name']),
            'distribution_roles' => Store::DISTRIBUTION_ROLES,
            'product_types' => ['drug', 'consumable', 'utility'],
            // the product form shows the half/piece sale fields only when the hospital settings allow them (as the web form)
            'sale_units' => ['allow_half_sale' => (bool) optional(\App\Models\ApplicationStatu::find(1))->allow_halve_sale, 'allow_piece_sale' => (bool) optional(\App\Models\ApplicationStatu::find(1))->allow_piece_sale],
            'managers' => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['ADMIN', 'SUPERADMIN', 'super-admin', 'PHARMACIST', 'STORE', 'NURSE']))
                ->orderBy('surname')->get(['id', 'surname', 'firstname', 'othername'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => trim($u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : ''))]),
        ]);
    }

    /** Store Governance, Section A and C: the store role catalogue and ownership (the web page's table). */
    public function governanceStores()
    {
        Gate::authorize('store-governance.view');

        return response()->json(['data' => Store::with(['manager', 'ward', 'department', 'parentStore'])->orderBy('store_name')->get()->map(fn ($s) => [
            'id' => $s->id, 'store_name' => $s->store_name, 'distribution_role' => $s->distribution_role,
            'allows_direct_patient_dispense' => (bool) $s->allows_direct_patient_dispense, 'requires_shift_context' => (bool) $s->requires_shift_context,
            'parent_store_id' => $s->parent_store_id, 'parent_store' => $s->parentStore?->store_name,
            'ward_id' => $s->ward_id, 'ward' => $s->ward?->name, 'department_id' => $s->department_id, 'department' => $s->department?->name,
            'manager_id' => $s->manager_id, 'manager' => $s->manager ? trim($s->manager->surname . ' ' . $s->manager->firstname) : null,
        ])]);
    }

    /** Store Governance, Section D to F: the context resolution rules (the web page's data, as JSON). */
    public function contextRules()
    {
        Gate::authorize('store-governance.view');

        $rule = fn ($r) => [
            'id' => $r->id, 'rule_type' => $r->rule_type, 'user_role' => $r->user_role, 'department_id' => $r->department_id,
            'department' => $r->department?->name, 'store_id' => $r->store_id, 'store' => $r->store?->store_name,
            'type_filter' => $r->type_filter, 'fallback_action' => $r->fallback_action, 'notes' => $r->notes,
        ];

        return response()->json([
            'role_rules' => StoreContextRule::where('rule_type', 'role_default')->with('store')->get()->map($rule),
            'department_rules' => StoreContextRule::where('rule_type', 'department_override')->with(['department', 'store'])->get()->map($rule),
            'bucket_rules' => StoreContextRule::where('rule_type', 'type_bucket')->get()->map($rule),
            'fallback_rule' => ($f = StoreContextRule::where('rule_type', 'fallback_behavior')->first()) ? $rule($f) : null,
            'roles' => \Spatie\Permission\Models\Role::orderBy('name')->pluck('name'),
            'stores' => Store::active()->orderBy('store_name')->get(['id', 'store_name']),
            'test_users' => User::where('is_admin', '!=', 19)->where('status', 1)->orderBy('surname')->limit(300)->get(['id', 'surname', 'firstname'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => trim($u->surname . ' ' . $u->firstname)]),
        ]);
    }
}
