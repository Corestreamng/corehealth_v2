<?php

namespace App\Http\Controllers\API;

use App\Helpers\BatchHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\StoreWorkbenchController;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use App\Models\Store;
use App\Models\StoreRequisition;
use App\Models\StoreStock;
use App\Services\RequisitionService;
use App\Services\StockService;
use App\Services\StoreContextResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Staff app, Store and Staff Supplies (requisitions, My Stock). Adapters only: where the web controller returns a
 * Blade view this answers the same data as JSON, from the same services and models. Everything that changes stock or
 * the ledger goes to the web controllers (observers post the journals), see routes/api.php.
 */
class MobileStoreController extends Controller
{
    public function __construct(
        protected StockService $stock,
        protected RequisitionService $requisitions,
        protected StoreContextResolver $resolver
    ) {
    }

    private function isStoreAdmin(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['ADMIN', 'SUPERADMIN', 'super-admin', 'STORE']);
    }

    /** The stores this user may use, the one the web would open, and what the user may override. */
    public function context(Request $request)
    {
        $user = $request->user();
        $stores = Store::active()->forUser($user)->orderBy('store_name')
            ->get(['id', 'store_name', 'code', 'store_type', 'distribution_role']);
        $resolved = $this->resolver->resolve($user);

        return response()->json([
            'success' => true,
            'stores' => $stores,
            'resolved_store_id' => $resolved?->id,
            'resolved_automatically' => (bool) $resolved,
            'can' => [
                'store_admin' => $this->isStoreAdmin(),
                'override_lane' => $user->hasPermissionTo('store-policy.override-lane'),
                'override_fifo' => $user->hasPermissionTo('store-policy.override-fifo'),
                'all_stores_my_stock' => $user->hasPermissionTo('stores.candidate-all'),
                'accounts' => $user->hasAnyRole(['ADMIN', 'SUPERADMIN', 'super-admin', 'ACCOUNTS']),
            ],
        ]);
    }

    /** Stats, pending counts, low stock and expiring batches for a store (or all stores). */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $storeId = ((int) $request->input('store_id')) ?: null;
        if ($storeId && !Store::active()->forUser($user)->where('id', $storeId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Access denied to this store.'], 403);
        }
        $wb = app(StoreWorkbenchController::class);
        $stats = $storeId ? $wb->getStoreStats($storeId) : $wb->getAllStoresStats();
        $pending = $storeId ? $wb->pendingActions(new Request(['store_id' => $storeId, 'counts_only' => 1]))->getData(true)['counts'] : null;

        $low = $this->stock->getLowStockProducts($storeId)->take(50)->map(fn ($s) => [
            'product_id' => $s->product_id,
            'product_name' => $s->product->product_name ?? '—',
            'current_quantity' => (int) $s->current_quantity,
            'reorder_level' => (int) ($s->reorder_level ?? 0),
            'store_id' => $s->store_id,
        ])->values();
        $expiring = $this->stock->getExpiringBatches($storeId, 30)->take(50)->map(fn ($b) => $this->batchRow($b))->values();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'pending' => $pending,
            'low_stock' => $low,
            'expiring' => $expiring,
        ]);
    }

    private function batchRow($b): array
    {
        return [
            'id' => $b->id,
            'batch_number' => $b->batch_number ?? '—',
            'batch_name' => $b->batch_name,
            'product_id' => $b->product_id,
            'product_name' => $b->product->product_name ?? ($b->product_name ?? '—'),
            'store_id' => $b->store_id,
            'current_qty' => (int) $b->current_qty,
            'cost_price' => (float) $b->cost_price,
            'expiry_date' => optional($b->expiry_date)->format('Y-m-d'),
            'supplier' => $b->supplier->company_name ?? null,
            'source' => $b->source,
            'is_donation' => (bool) $b->is_donation,
        ];
    }

    /** A product's batches in a store (the web "product batches" page). */
    public function productBatches(Request $request, int $productId)
    {
        $request->validate(['store_id' => 'required|exists:stores,id']);
        $this->authorizeStore((int) $request->store_id);
        $product = Product::with('packagings')->findOrFail($productId);
        $batches = StockBatch::where('store_id', $request->store_id)->where('product_id', $productId)
            ->with('supplier')->orderByDesc('is_active')->orderBy('expiry_date')->orderBy('created_at')->limit(300)->get()
            ->map(fn ($b) => $this->batchRow($b) + [
                'initial_qty' => (int) $b->initial_qty,
                'is_active' => (bool) $b->is_active,
                'voided' => (bool) $b->voided_at,
                'received_date' => optional($b->received_date)->format('Y-m-d'),
                'acquisition_type' => $b->acquisition_type,
            ]);

        return response()->json([
            'success' => true,
            'product' => ['id' => $product->id, 'name' => $product->product_name, 'code' => $product->product_code, 'base_unit' => $product->base_unit_name,
                'packagings' => $product->packagings->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'base_unit_qty' => $p->base_unit_qty])],
            'batches' => $batches,
            'in_stock' => (int) StoreStock::where('store_id', $request->store_id)->where('product_id', $productId)->value('current_quantity'),
        ]);
    }

    private function authorizeStore(int $storeId): void
    {
        abort_unless(Store::active()->forUser(auth()->user())->where('id', $storeId)->exists(), 403, 'Access denied to this store.');
    }

    /** Batches expiring or expired, paged (the web expiry report). */
    public function expiry(Request $request)
    {
        $storeId = ((int) $request->input('store_id')) ?: null;
        if ($storeId) {
            $this->authorizeStore($storeId);
        }
        $days = max(1, min(730, (int) $request->input('days', 90)));
        $rows = collect(BatchHelper::getBatchesWithExpiryWarning($storeId, $days))->map(fn ($w) => $this->batchRow($w['batch']) + [
            'days_to_expiry' => $w['days_to_expiry'],
            'warning_level' => $w['warning_level'],
            'is_expired' => $w['is_expired'],
            'value' => round((int) $w['batch']->current_qty * (float) $w['batch']->cost_price, 2),
        ]);
        if ($level = $request->input('level')) {
            $rows = $rows->where('warning_level', $level);
        }
        if ($term = strtolower(trim((string) $request->search))) {
            $rows = $rows->filter(fn ($r) => str_contains(strtolower($r['product_name'] . ' ' . $r['batch_number']), $term));
        }

        return $this->page($rows->values(), $request, [
            'summary' => [
                'expired' => $rows->where('is_expired', true)->count(),
                'value_at_risk' => round($rows->sum('value'), 2),
            ],
        ]);
    }

    /** Stock value by product, paged (the web stock value report). */
    public function stockValue(Request $request)
    {
        $storeId = ((int) $request->input('store_id')) ?: null;
        if ($storeId) {
            $this->authorizeStore($storeId);
        }
        $report = $this->stock->getStockValueReport($storeId);
        $rows = collect($report['products'])->filter(fn ($p) => data_get($p, 'product.id'))->map(fn ($p) => [
            'product_id' => data_get($p, 'product.id'),
            'product_name' => data_get($p, 'product.product_name'),
            'product_code' => data_get($p, 'product.product_code'),
            'total_qty' => (int) $p['total_qty'],
            'total_value' => (float) $p['total_value'],
        ])->sortByDesc('total_value')->values();
        if ($term = strtolower(trim((string) $request->search))) {
            $rows = $rows->filter(fn ($r) => str_contains(strtolower($r['product_name'] . ' ' . $r['product_code']), $term))->values();
        }

        return $this->page($rows, $request, ['summary' => ['total_value' => (float) $report['total_value'], 'products' => count($report['products'])]]);
    }

    private function page($rows, Request $request, array $extra = [])
    {
        $per = max(1, min(100, (int) $request->input('per_page', 25)));
        $page = max(1, (int) $request->input('page', 1));

        return response()->json([
            'success' => true,
            'data' => $rows->forPage($page, $per)->values(),
            'total' => $rows->count(),
            'page' => $page,
            'per_page' => $per,
        ] + $extra);
    }

    /** A requisition with its items, stock available to fulfil, and what this user may do (the web show page). */
    public function requisition(Request $request, StoreRequisition $requisition)
    {
        $user = $request->user();
        $requisition->load(['fromStore', 'toStore', 'requester', 'approver', 'fulfiller', 'items.product.packagings', 'items.packaging', 'items.sourceBatch', 'items.destinationBatch']);
        $canAct = Gate::forUser($user)->allows('can-approve-requisition-for-store', $requisition->fromStore);

        return response()->json([
            'success' => true,
            'requisition' => $requisition,
            'available_stock' => $requisition->canFulfill() ? $this->requisitions->getAvailableStockForRequisition($requisition) : null,
            'can' => [
                'approve' => $requisition->canApprove() && $canAct,
                'reject' => $requisition->canReject() && $canAct,
                'fulfill' => $requisition->canFulfill() && ($canAct || $this->isStoreAdmin()),
                'cancel' => $requisition->canCancel(),
                'edit_header' => $requisition->canEditHeader(),
                'edit_items' => $requisition->canEditItems(),
            ],
        ]);
    }

    /** Stores and products for the new-requisition form (the web create page's data). */
    public function requisitionFormData(Request $request)
    {
        $user = $request->user();
        $stores = Store::active()->orderBy('store_name')->get(['id', 'store_name', 'store_type', 'distribution_role']);
        $mine = $this->resolver->candidateStores($user)->pluck('id')->values();

        return response()->json([
            'success' => true,
            'stores' => $stores,
            'my_store_ids' => $mine,
            'resolved_store_id' => $this->resolver->resolve($user)?->id,
        ]);
    }

    /** Product search for forms; with store_id the stock in that store comes along. */
    public function searchProducts(Request $request)
    {
        $term = trim((string) $request->input('term', $request->input('q', '')));
        $q = Product::with(['packagings'])->where('status', 1);
        if ($term !== '') {
            $q->where(fn ($w) => $w->where('product_name', 'like', "%{$term}%")->orWhere('product_code', 'like', "%{$term}%"));
        }
        $products = $q->orderBy('product_name')->limit(30)->get();
        $stock = $request->filled('store_id')
            ? StoreStock::where('store_id', $request->store_id)->whereIn('product_id', $products->pluck('id'))->pluck('current_quantity', 'product_id')
            : collect();

        return response()->json(['success' => true, 'products' => $products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->product_name,
            'code' => $p->product_code,
            'base_unit' => $p->base_unit_name ?? 'Piece',
            'allow_decimal' => (bool) $p->allow_decimal_qty,
            'in_store' => $request->filled('store_id') ? (int) ($stock[$p->id] ?? 0) : null,
            'packagings' => $p->packagings->map(fn ($k) => ['id' => $k->id, 'name' => $k->name, 'base_unit_qty' => $k->base_unit_qty]),
        ])]);
    }

    /** A purchase order with items, payments and what may be done to it (the web show page). */
    public function purchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $po = $purchaseOrder->load(['supplier', 'targetStore', 'items.product.packagings', 'items.packaging', 'payments.bank', 'creator', 'approver']);

        return response()->json([
            'success' => true,
            'purchase_order' => $po,
            'totals' => ['total' => (float) $po->total_amount, 'paid' => (float) $po->amount_paid, 'balance_due' => (float) $po->balance_due],
            'can' => [
                'edit' => $po->canEdit(),
                'submit' => $po->canSubmit(),
                'approve' => $po->canApprove(),
                'receive' => $po->canReceive(),
                'cancel' => $po->canCancel(),
                'record_payment' => $po->canRecordPayment(),
            ],
        ]);
    }

    /** My Stock: the stores the user works from, the active one, all stores and categories (the web page's data). */
    public function myStockContext(Request $request)
    {
        $user = $request->user();
        $mine = $this->resolver->candidateStores($user);
        $active = null;
        if ($request->filled('store_id') && ($user->hasPermissionTo('stores.candidate-all') || $mine->contains('id', (int) $request->store_id))) {
            $active = Store::find($request->store_id);
        }
        $active = $active ?? $this->resolver->resolve($user) ?? $mine->first();
        $pick = fn ($s) => ['id' => $s->id, 'store_name' => $s->store_name, 'store_type' => $s->store_type];

        return response()->json([
            'success' => true,
            'active_store' => $active ? $pick($active) : null,
            'my_stores' => $mine->map($pick)->values(),
            'stores' => Store::active()->orderBy('store_name')->get()->map($pick)->values(),
            'categories' => \App\Models\ProductCategory::orderBy('category_name')->get(['id', 'category_name']),
            'can_view_all' => $user->hasPermissionTo('stores.candidate-all'),
        ]);
    }
}
