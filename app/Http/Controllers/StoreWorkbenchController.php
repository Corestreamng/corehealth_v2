<?php

namespace App\Http\Controllers;

use App\Helpers\BatchHelper;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use App\Models\StockBatchTransaction;
use App\Models\Store;
use App\Models\StoreContextRule;
use App\Models\StoreDamage;
use App\Models\StoreRequisition;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use App\Services\RequisitionService;
use App\Services\StockService;
use App\Services\StoreContextResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

/**
 * Controller: StoreWorkbenchController
 *
 * Plan Reference: Phase 4 - Controllers
 * Purpose: Store Workbench for managing inventory, stock, and transfers
 *
 * Features:
 * - Dashboard with stock overview
 * - Stock batch management
 * - Expiry monitoring
 * - Low stock alerts
 * - Stock adjustments
 * - Transfer management
 *
 * Permissions Required:
 * - store.view: Access store workbench
 * - store.manage: Manage stock and adjustments
 * - store.transfer: Handle transfers
 *
 * Related Models: Store, StockBatch, StoreStock, Product
 * Related Files:
 * - app/Services/StockService.php
 * - resources/views/store_workbench/
 */
class StoreWorkbenchController extends Controller
{
    protected StockService $stockService;

    protected PurchaseOrderService $purchaseOrderService;

    protected RequisitionService $requisitionService;

    protected StoreContextResolver $contextResolver;

    public function __construct(
        StockService $stockService,
        PurchaseOrderService $purchaseOrderService,
        RequisitionService $requisitionService,
        StoreContextResolver $contextResolver
    ) {
        $this->stockService = $stockService;
        $this->purchaseOrderService = $purchaseOrderService;
        $this->requisitionService = $requisitionService;
        $this->contextResolver = $contextResolver;
    }

    /**
     * Store Workbench Dashboard
     *
     * Plan §6.4 (Store Keeper Workbench), §10 (Context Resolution):
     * If no store_id is given, StoreContextResolver auto-resolves the store for the
     * current user. The resolved store + fallback action are passed to the view for
     * the store context badge and "Resolve Store Context" banner (Plan §6.1).
     */
    public function index(Request $request)
    {
        $storeId = $request->get('store_id');

        // ── Store Governance: context resolution (Plan §10, §B2) ─────────────
        $resolvedAutomatically = false;
        $contextFallbackAction = null;

        if ($storeId) {
            $store = Store::find($storeId);
        } else {
            $store = $this->contextResolver->resolve(auth()->user());
            $resolvedAutomatically = (bool) $store;
            if (!$store) {
                $contextFallbackAction = StoreContextRule::fallbackAction();
            }
            $storeId = $store?->id;
        }
        // ─────────────────────────────────────────────────────────────────────

        $stores = Store::active()->forUser(auth()->user())->orderBy('store_name')->get();

        // If no store selected, show aggregated stats for all stores
        if (!$store) {
            // Dashboard statistics for all stores
            $stats = $this->getAllStoresStats();

            // Pending actions across all stores
            $pendingPOs = $this->purchaseOrderService->getReadyToReceive();
            $pendingRequisitions = collect(); // Would need to aggregate
            $incomingRequisitions = collect();

            // Alerts across all stores
            $expiringBatches = $this->stockService->getExpiringBatches(null, 30);
            $lowStockItems = $this->stockService->getLowStockProducts(null);
        } else {
            // Dashboard statistics for specific store
            $stats = $this->getStoreStats($storeId);

            // Pending actions
            $pendingPOs = \App\Models\PurchaseOrder::where('target_store_id', $storeId)
                ->whereIn('status', ['approved', 'partial', 'partial_received', 'partially_received'])
                ->get();
            $pendingRequisitions = $this->requisitionService->getPendingFulfillment($storeId);
            $incomingRequisitions = $this->requisitionService->getMyRequisitions($storeId)
                ->whereIn('status', ['pending', 'approved', 'partial']);

            // Alerts
            $expiringBatches = $this->stockService->getExpiringBatches($storeId, 30);
            $lowStockItems = $this->stockService->getLowStockProducts($storeId);
        }

        return view('admin.inventory.store-workbench.index', compact(
            'store',
            'stores',
            'stats',
            'pendingPOs',
            'pendingRequisitions',
            'incomingRequisitions',
            'expiringBatches',
            'lowStockItems',
            'resolvedAutomatically',   // Plan §6.1 — context badge: "Auto-resolved"
            'contextFallbackAction'    // Plan §6.1 — drives "Resolve Store Context" banner when null
        ));
    }

    /**
     * Get aggregated stats for all stores
     */
    protected function getAllStoresStats(): array
    {
        return [
            'total_products' => StoreStock::where('is_active', true)->distinct('product_id')->count('product_id'),
            'total_batches' => StockBatch::active()->hasStock()->count(),
            'low_stock_count' => $this->stockService->getLowStockProducts(null)->count(),
            'expiring_soon' => $this->stockService->getExpiringBatches(null, 30)->count(),
            'total_value' => StockBatch::active()->hasStock()->sum(\DB::raw('current_qty * cost_price')),
        ];
    }

    /**
     * Stock overview (list all products with stock in store)
     */
    public function stockOverview(Request $request)
    {
        $storeId = $request->get('store_id', Store::getDefaultPharmacy()?->id);
        $store = $storeId ? Store::find($storeId) : null;

        // Allow viewing all stores or specific store
        $selectedStore = $store;

        if ($request->ajax()) {
            $query = StoreStock::with(['product.category', 'store'])
                ->where('is_active', true);

            // Filter by store if specified
            if ($storeId) {
                $query->where('store_id', $storeId);
            }

            // Filter by product if specified (coming from products page)
            if ($request->filled('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('product_name', 'like', "%{$search}%")
                        ->orWhere('product_code', 'like', "%{$search}%");
                });
            }

            if ($request->filled('filter') && $request->filter === 'low') {
                $query->whereRaw('current_quantity <= reorder_level');
            }

            if ($request->filled('low_stock_only') && $request->low_stock_only) {
                $query->whereRaw('current_quantity <= reorder_level');
            }

            return DataTables::of($query)
                ->addColumn('product_name', fn ($ss) => $ss->product?->product_name ?? '-')
                ->addColumn('product_code', fn ($ss) => $ss->product?->product_code ?? '-')
                ->addColumn('category', fn ($ss) => $ss->product?->category?->category_name ?? '-')
                ->addColumn('store_name', fn ($ss) => $ss->store->store_name ?? '-')
                ->addColumn('qty_display', fn ($ss) => $this->formatQuantityDisplay($ss))
                ->addColumn('batches_count', fn ($ss) => StockBatch::where('product_id', $ss->product_id)
                    ->where('store_id', $ss->store_id)
                    ->active()
                    ->hasStock()
                    ->count())
                ->addColumn('actions', fn ($ss) => $this->getStockActionButtons($ss))
                ->rawColumns(['qty_display', 'actions'])
                ->make(true);
        }

        $stores = Store::active()->forUser(auth()->user())->orderBy('store_name')->get();

        // Pass filter info for display
        $productFilter = $request->filled('product_id') ? Product::find($request->product_id) : null;

        // Build products query for non-AJAX rendering
        $productsQuery = Product::with(['category', 'storeStock']);

        // Filter by specific product if requested (from products page link) - bypass storeStock requirement
        if ($request->filled('product_id')) {
            $productsQuery->where('id', $request->product_id);
        } else {
            // Only require storeStock when viewing general stock overview (not specific product)
            $productsQuery->whereHas('storeStock', function ($q) use ($storeId) {
                $q->where('is_active', true);
                if ($storeId) {
                    $q->where('store_id', $storeId);
                }
            });
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $productsQuery->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category_id')) {
            $productsQuery->where('category_id', $request->category_id);
        }

        // Get products with their stock info
        $products = $productsQuery->orderBy('product_name')->paginate(25);

        // Add stock info to each product
        foreach ($products as $product) {
            // Get store stock for this product
            $storeStock = $storeId
                ? $product->storeStock->where('store_id', $storeId)->first()
                : $product->storeStock->first();

            $product->store_stock = $storeStock;

            // Calculate available quantity from active batches
            $batchQuery = StockBatch::where('product_id', $product->id)
                ->active()
                ->hasStock();

            if ($storeId) {
                $batchQuery->where('store_id', $storeId);
            }

            $product->available_qty = $batchQuery->sum('current_qty');
            $product->batches_count = $batchQuery->count();

            // Filter: low stock
            if ($request->filled('filter') && $request->filter === 'low') {
                $reorderLevel = $storeStock->reorder_level ?? 10;
                if ($product->available_qty > $reorderLevel) {
                    continue; // Skip products that aren't low stock
                }
            }

            // Filter: out of stock
            if ($request->filled('filter') && $request->filter === 'out') {
                if ($product->available_qty > 0) {
                    continue;
                }
            }
        }

        // Get categories for filter
        $categories = \App\Models\ProductCategory::orderBy('category_name')->get();

        return view('admin.inventory.store-workbench.stock-overview', compact(
            'selectedStore',
            'stores',
            'productFilter',
            'products',
            'categories'
        ));
    }

    /**
     * View batches for a specific product in store
     */
    public function productBatches(Request $request, int $productId)
    {
        $product = Product::findOrFail($productId);
        $user = auth()->user();
        $stores = Store::active()->forUser($user)->orderBy('store_name')->get();

        // Get store filter if specified, otherwise resolve default store
        $storeId = $request->get('store_id');
        if (!$storeId) {
            $resolved = $this->contextResolver->resolve($user);
            $storeId = $resolved?->id ?? $stores->first()?->id;
        }
        $selectedStore = $storeId ? Store::find($storeId) : null;

        // Ensure the requested store is within the user's accessible stores
        if ($selectedStore && !$stores->contains('id', $selectedStore->id)) {
            abort(403, 'You do not have access to this store.');
        }

        // Build query for batches
        $batchQuery = StockBatch::where('product_id', $productId)
            ->active()
            ->with(['creator', 'store', 'supplier', 'purchaseOrderItem.purchaseOrder'])
            ->fifoOrder();

        // Always scope query to resolved/selected store if any exists
        if ($storeId) {
            $batchQuery->where('store_id', $storeId);
        }

        $batches = $batchQuery->get();

        // Add expiry info to each batch
        $batches = $batches->map(function ($batch) {
            $batch->expiry_status = $this->getExpiryStatus($batch);

            return $batch;
        });

        // Calculate totals
        $totalStock = $batches->sum('current_qty');
        $totalBatches = $batches->count();
        $suppliers = Supplier::orderBy('company_name')->get();

        return view('admin.inventory.store-workbench.product-batches', compact(
            'product',
            'batches',
            'stores',
            'selectedStore',
            'totalStock',
            'totalBatches',
            'suppliers'
        ));
    }

    /**
     * Stock adjustment form
     */
    public function adjustmentForm(Request $request, int $batchId)
    {
        $batch = StockBatch::with(['product', 'store'])->findOrFail($batchId);

        return view('admin.inventory.store-workbench.adjustment-form', compact('batch'));
    }

    /**
     * Process stock adjustment
     */
    public function processAdjustment(Request $request, int $batchId)
    {
        $request->validate([
            'adjustment_type' => 'required|in:add,subtract',
            'qty' => 'required|integer|min:1',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $qty = $request->adjustment_type === 'subtract' ? -$request->qty : $request->qty;

            $transaction = $this->stockService->adjustStock($batchId, $qty, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'new_balance' => $transaction->balance_after,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Write off expired stock
     */
    public function writeOffExpired(Request $request, int $batchId)
    {
        $request->validate([
            'qty' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $batch = StockBatch::findOrFail($batchId);
            $qty = $request->qty ?? $batch->current_qty;

            if ($qty > $batch->current_qty) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot write off {$qty} units. Only {$batch->current_qty} units available.",
                ], 400);
            }

            $damage = StoreDamage::create([
                'product_id' => $batch->product_id,
                'store_id' => $batch->store_id,
                'batch_id' => $batch->id,
                'qty_damaged' => $qty,
                'unit_cost' => $batch->cost_price,
                'total_value' => round($batch->cost_price * $qty, 2),
                'damage_type' => 'expired',
                'damage_reason' => $request->notes ?? 'Expired stock write-off',
                'discovered_date' => now()->toDateString(),
                'status' => StoreDamage::STATUS_APPROVED,
                'created_by' => auth()->id() ?? 1,
                'approved_by' => auth()->id() ?? 1,
                'approved_at' => now(),
                'approval_notes' => 'Direct tally card expired write-off',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expired stock written off and journal entry created',
                'new_balance' => $batch->fresh()->current_qty,
                'damage_id' => $damage->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Write off damaged stock
     */
    public function writeOffDamaged(Request $request, int $batchId)
    {
        $request->validate([
            'qty' => 'required|integer|min:1',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $batch = StockBatch::findOrFail($batchId);
            $qty = (int) $request->qty;

            if ($qty > $batch->current_qty) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot write off {$qty} units. Only {$batch->current_qty} units available.",
                ], 400);
            }

            $damage = StoreDamage::create([
                'product_id' => $batch->product_id,
                'store_id' => $batch->store_id,
                'batch_id' => $batch->id,
                'qty_damaged' => $qty,
                'unit_cost' => $batch->cost_price,
                'total_value' => round($batch->cost_price * $qty, 2),
                'damage_type' => 'damaged',
                'damage_reason' => $request->reason,
                'discovered_date' => now()->toDateString(),
                'status' => StoreDamage::STATUS_APPROVED,
                'created_by' => auth()->id() ?? 1,
                'approved_by' => auth()->id() ?? 1,
                'approved_at' => now(),
                'approval_notes' => 'Direct tally card damage write-off',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Damaged stock written off and journal entry created',
                'new_balance' => $batch->fresh()->current_qty,
                'damage_id' => $damage->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update metadata on an existing stock batch
     */
    public function updateBatch(Request $request, $batch)
    {
        $batchId = (int) $batch;
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'batch_number' => 'required|string|max:100',
            'batch_name' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date',
            'cost_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $batch = $this->stockService->updateBatchMetadata($batchId, $request->all());

            return response()->json([
                'success' => true,
                'message' => "Batch {$batch->batch_number} updated successfully",
                'batch' => $batch,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update batch: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Void a stock batch recorded in error (reverses physical stock & GL intake)
     */
    public function voidBatch(Request $request, $batch)
    {
        $batchId = (int) $batch;
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $batch = $this->stockService->voidBatch($batchId, $request->reason, auth()->id() ?? 1);

            return response()->json([
                'success' => true,
                'message' => "Batch {$batch->batch_number} has been voided and ledger intake reversed.",
                'batch' => $batch,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to void batch: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Void an adjustment transaction recorded in error
     */
    public function voidAdjustment(Request $request, $transaction)
    {
        $transactionId = (int) $transaction;
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $tx = $this->stockService->voidAdjustment($transactionId, $request->reason, auth()->id() ?? 1);

            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment voided and variance ledger entry reversed.',
                'new_balance' => $tx->stockBatch->fresh()->current_qty,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to void adjustment: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Process a manual batch return to supplier
     */
    public function returnBatch(Request $request, $batch)
    {
        $batchId = (int) $batch;
        $request->validate([
            'qty_returned' => 'required|integer|min:1',
            'return_reason' => 'required|string|in:damaged,expired_near,recalled,wrong_item,excess,quality_issue,other',
            'settlement_type' => 'required|string|in:ap_credit_note,cash_refund,receivable,no_financial_impact',
            'return_notes' => 'nullable|string|max:500',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        try {
            $return = $this->stockService->returnBatch($batchId, $request->all(), auth()->id() ?? 1);

            return response()->json([
                'success' => true,
                'message' => "Batch return #{$return->return_number} processed successfully.",
                'return' => $return,
                'new_balance' => $return->stockBatch->fresh()->current_qty,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process batch return: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Preview real-time General Ledger impact for storekeeper UI modals
     */
    public function previewJournalImpact(Request $request)
    {
        $action = $request->action ?? $request->operation; // intake, adjustment, damage, return, void_batch, void_adjustment
        $storeId = $request->store_id;
        $qty = (int) ($request->qty ?? 1);
        $costPrice = (float) ($request->cost_price ?? 0);
        $total = round($qty * $costPrice, 2);

        $store = Store::find($storeId);
        $isPharmacy = $store && in_array($store->distribution_role, [Store::ROLE_PHARMACY_HUB, Store::ROLE_PHARMACY_SATELLITE], true);
        $isLab = $store && $store->distribution_role === Store::ROLE_LAB;
        $invName = $isPharmacy ? '1300 - Inventory - Pharmacy' : ($isLab ? '1320 - Inventory - Laboratory' : '1310 - Inventory - Medical Supplies');

        $preview = [
            'total_value' => $total,
            'debit_account' => '',
            'debit_amount' => $total,
            'credit_account' => '',
            'credit_amount' => $total,
            'explanation' => '',
            'is_monetary' => $total > 0,
        ];

        if ($action === 'intake') {
            $acq = $request->acquisition_type ?? 'credit';
            $supplier = Supplier::find($request->supplier_id);
            $supName = $supplier ? $supplier->company_name : 'Selected Supplier';

            if ($acq === 'credit') {
                $preview['debit_account'] = $invName;
                $preview['credit_account'] = "2110 - Accounts Payable ({$supName} Sub-Account)";
                $preview['explanation'] = "Increases inventory asset and creates accounts payable liability owed to {$supName}.";
            } elseif ($acq === 'cash') {
                $preview['debit_account'] = $invName;
                $preview['credit_account'] = '1030 - Petty Cash / Till';
                $preview['explanation'] = 'Increases inventory asset and reduces cash in till.';
            } elseif ($acq === 'opening_stock') {
                $preview['debit_account'] = $invName;
                $preview['credit_account'] = '3100 - Retained Earnings (Opening Equity)';
                $preview['explanation'] = 'Initial inventory capitalization via opening balance equity.';
            } elseif ($acq === 'donation') {
                $preview['debit_account'] = "{$invName} (₦0.00)";
                $preview['credit_account'] = 'No Financial Payable (Logged Program Stock)';
                $preview['is_monetary'] = false;
                $preview['explanation'] = 'Physical stock is increased without generating financial liability.';
            }
        } elseif ($action === 'adjustment') {
            $type = $request->adjustment_type; // add or subtract
            if ($type === 'add') {
                $preview['debit_account'] = $invName;
                $preview['credit_account'] = '4230 - Stock Variance Surplus Gain (Other Income)';
                $preview['explanation'] = 'Recognizes surplus physical stock found as non-operating inventory gain.';
            } else {
                $preview['debit_account'] = '5050 - Inventory Shrinkage Loss (Operating Expense)';
                $preview['credit_account'] = $invName;
                $preview['explanation'] = 'Writes off missing stock to shrinkage operating expense and reduces inventory asset.';
            }
        } elseif ($action === 'damage') {
            $dmgType = $request->damage_type ?? 'damaged';
            $expAccount = $dmgType === 'expired' ? '5040 - Expired Stock Write-off' : '5030 - Damaged Goods Write-off';
            $preview['debit_account'] = "{$expAccount} (Operating Loss)";
            $preview['credit_account'] = $invName;
            $preview['explanation'] = "Recognizes unrecoverable {$dmgType} stock as an operating expense loss.";
        } elseif ($action === 'return') {
            $settle = $request->settlement_type ?? 'ap_credit_note';
            $supplier = Supplier::find($request->supplier_id);
            $supName = $supplier ? $supplier->company_name : 'Selected Supplier';

            if ($settle === 'cash_refund') {
                $preview['debit_account'] = '1010 - Cash in Hand / Petty Cash';
                $preview['credit_account'] = $invName;
                $preview['explanation'] = 'Cash refund received back from vendor and inventory removed.';
            } elseif ($settle === 'receivable') {
                $preview['debit_account'] = "1200 - Accounts Receivable ({$supName})";
                $preview['credit_account'] = $invName;
                $preview['explanation'] = 'Vendor owes hospital a refund and inventory is removed.';
            } else {
                $preview['debit_account'] = "2110 - Accounts Payable ({$supName} Credit Note)";
                $preview['credit_account'] = $invName;
                $preview['explanation'] = "Reduces outstanding liability owed to {$supName} and removes inventory.";
            }
        } elseif ($action === 'void_batch') {
            $preview['debit_account'] = '2110 AP / 1030 Cash (Original Credit Source)';
            $preview['credit_account'] = $invName;
            $preview['explanation'] = 'Clean reversal of original intake entry. Does not hit shrinkage or loss accounts.';
        } elseif ($action === 'void_adjustment') {
            $preview['debit_account'] = 'Reverses Variance Gain/Loss Entry';
            $preview['credit_account'] = 'Restores Inventory Balance';
            $preview['explanation'] = 'Cleanses erroneous count adjustment and restores original batch quantity.';
        }

        return response()->json([
            'success' => true,
            'preview' => $preview,
        ]);
    }

    /**
     * Manual batch entry form
     */
    public function manualBatchForm(Request $request)
    {
        $user = auth()->user();
        $stores = Store::active()->forUser($user)->orderBy('store_name')->get();

        // Resolve default store: explicit request param → context resolver → first accessible store
        $storeId = $request->get('store_id');
        if (!$storeId) {
            $resolved = $this->contextResolver->resolve($user);
            $storeId = $resolved?->id ?? $stores->first()?->id;
        }
        $store = $storeId ? Store::find($storeId) : null;

        // Ensure the requested store is within the user's accessible stores
        if ($store && !$stores->contains('id', $store->id)) {
            abort(403, 'You do not have access to this store.');
        }

        $products = Product::where('status', 1)->orderBy('product_name')->get();

        // Pre-select product if coming from products page
        $selectedProductId = $request->get('product_id');
        $selectedProduct = $selectedProductId ? Product::find($selectedProductId) : null;

        return view('admin.inventory.store-workbench.manual-batch-form', compact('store', 'stores', 'products', 'selectedProduct'));
    }

    /**
     * Create manual batch entry
     */
    public function createManualBatch(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_id' => 'required|exists:products,id',
        ]);

        // Governance: verify the submitted store is accessible by this user
        $accessibleStoreIds = Store::active()->forUser(auth()->user())->pluck('id');
        if (!$accessibleStoreIds->contains((int) $request->store_id)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'You are not authorised to add stock to this store.'], 403);
            }
            abort(403, 'You are not authorised to add stock to this store.');
        }

        $isDonation = $request->boolean('is_donation') || $request->reference_type === 'donation';

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'quantity' => 'required|integer|min:1',
            'cost_price' => $isDonation ? 'nullable|numeric|min:0' : 'required|numeric|gt:0',
            'expiry_date' => 'nullable|date|after:today',
            'batch_name' => 'nullable|string|max:100',
            'batch_number' => 'required|string|max:100|unique:stock_batches,batch_number',
            'acquisition_type' => 'nullable|string|in:credit,cash,donation,opening_stock',
            'payment_account_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:500',
            'packaging_id' => 'nullable|exists:product_packagings,id',
            'batch_packaging' => 'nullable|exists:product_packagings,id',
            'packaging_qty' => 'nullable|numeric|min:0',
            'is_donation' => 'nullable|boolean',
        ]);

        try {
            // Convert packaging-level cost to base-unit cost
            // The user enters cost per packaging unit (e.g. cost per Box of 100 tablets).
            // We divide by base_unit_qty to get cost per single base unit (e.g. per Tablet).
            $packagingId = $request->packaging_id ?? $request->batch_packaging;
            $costPrice = $isDonation ? (float) ($request->cost_price ?? 0) : (float) $request->cost_price;
            if ($packagingId && $costPrice > 0) {
                $packaging = \App\Models\ProductPackaging::find($packagingId);
                if ($packaging && $packaging->base_unit_qty > 1) {
                    $costPrice = $costPrice / $packaging->base_unit_qty;
                }
            }

            $batch = $this->stockService->createBatch([
                'product_id' => $request->product_id,
                'store_id' => $request->store_id,
                'supplier_id' => $request->supplier_id,
                'qty' => $request->quantity,
                'cost_price' => $costPrice,
                'expiry_date' => $request->expiry_date,
                'batch_name' => $request->batch_name,
                'batch_number' => $request->batch_number,
                'source' => StockBatch::SOURCE_MANUAL,
                'acquisition_type' => $request->acquisition_type ?? StockBatch::ACQUISITION_CREDIT,
                'payment_account_code' => $request->payment_account_code,
                'notes' => $request->notes ?? ($isDonation ? 'Donation received' : 'Manual entry'),
                'packaging_id' => $packagingId,
                'packaging_qty' => $request->packaging_qty,
                'is_donation' => $isDonation,
            ]);

            // Handle AJAX vs regular form submission
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Batch {$batch->batch_number} created successfully",
                    'batch' => $batch,
                ]);
            }

            return redirect()
                ->route('inventory.store-workbench.product-batches', ['product' => $request->product_id, 'store_id' => $request->store_id])
                ->with('success', "Batch {$batch->batch_number} created successfully with {$request->quantity} units");
        } catch (\Exception $e) {
            Log::error('Manual batch creation failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create batch: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create batch: ' . $e->getMessage());
        }
    }

    /**
     * Expiry report
     */
    public function expiryReport(Request $request)
    {
        $storeId = $request->get('store_id');
        $days = $request->get('days', 90);

        $expiringBatches = BatchHelper::getBatchesWithExpiryWarning($storeId, $days);
        $accessibleStores = Store::active()->forUser(auth()->user())->orderBy('store_name')->get();
        $stores = $accessibleStores;

        // Governance: if a specific store was requested, verify the user has access
        if ($storeId && !auth()->user()->hasAnyRole(['ADMIN', 'SUPERADMIN', 'super-admin'])) {
            if (!$accessibleStores->contains('id', (int) $storeId)) {
                abort(403, 'You are not authorised to view the expiry report for this store.');
            }
        }

        $store = $storeId ? Store::find($storeId) : null;

        return view('admin.inventory.store-workbench.expiry-report', compact('expiringBatches', 'stores', 'store', 'days'));
    }

    /**
     * Stock value report
     */
    public function stockValueReport(Request $request)
    {
        $storeId = $request->get('store_id');
        $stores = Store::active()->forUser(auth()->user())->orderBy('store_name')->get();

        // Governance: if a specific store was requested, verify the user has access
        if ($storeId && !auth()->user()->hasAnyRole(['ADMIN', 'SUPERADMIN', 'super-admin'])) {
            if (!$stores->contains('id', (int) $storeId)) {
                abort(403, 'You are not authorised to view the stock value report for this store.');
            }
        }

        // If no store_id provided or empty, show all stores report
        if (empty($storeId)) {
            $store = null;
            $report = $this->stockService->getStockValueReport(null); // All stores
        } else {
            $store = Store::findOrFail($storeId);
            $report = $this->stockService->getStockValueReport($storeId);
        }

        return view('admin.inventory.store-workbench.stock-value-report', compact('store', 'stores', 'report'));
    }

    /**
     * Get store statistics
     */
    protected function getStoreStats(int $storeId): array
    {
        $totalProducts = StoreStock::where('store_id', $storeId)
            ->where('is_active', true)
            ->where('current_quantity', '>', 0)
            ->count();

        $totalBatches = StockBatch::where('store_id', $storeId)
            ->active()
            ->hasStock()
            ->count();

        $totalValue = StockBatch::where('store_id', $storeId)
            ->active()
            ->selectRaw('SUM(current_qty * cost_price) as total')
            ->value('total') ?? 0;

        $lowStockCount = StoreStock::where('store_id', $storeId)
            ->where('is_active', true)
            ->whereRaw('current_quantity <= reorder_level')
            ->count();

        $expiringCount = StockBatch::where('store_id', $storeId)
            ->active()
            ->hasStock()
            ->expiringSoon(30)
            ->count();

        $expiredCount = StockBatch::where('store_id', $storeId)
            ->active()
            ->hasStock()
            ->expired()
            ->count();

        return [
            'total_products' => $totalProducts,
            'total_batches' => $totalBatches,
            'total_value' => $totalValue,
            'low_stock_count' => $lowStockCount,
            'expiring_count' => $expiringCount,
            'expired_count' => $expiredCount,
        ];
    }

    /**
     * Format quantity display with alert styling
     */
    protected function formatQuantityDisplay(StoreStock $ss): string
    {
        $qty = $ss->current_quantity;
        $reorderLevel = $ss->reorder_level ?? 10;

        // Show formatted qty with base unit name if product is loaded
        $product = $ss->relationLoaded('product') ? $ss->product : null;
        $unitLabel = $product ? ($product->base_unit_name ?? 'pcs') : 'pcs';
        $formatted = $product && method_exists($product, 'formatQty') ? $product->formatQty($qty) : number_format($qty) . ' ' . $unitLabel;

        if ($qty <= 0) {
            return "<span class='badge badge-danger'>Out of Stock</span>";
        } elseif ($qty <= $reorderLevel) {
            return "<span class='badge badge-warning'>{$formatted}</span> <small class='text-muted'>(Low)</small>";
        }

        return "<span class='text-success'>{$formatted}</span>";
    }

    /**
     * Get expiry status for a batch
     */
    protected function getExpiryStatus(StockBatch $batch): array
    {
        if (!$batch->expiry_date) {
            return ['status' => 'none', 'label' => 'No Expiry', 'class' => 'text-muted'];
        }

        $daysUntil = now()->diffInDays($batch->expiry_date, false);

        if ($daysUntil < 0) {
            return ['status' => 'expired', 'label' => 'Expired', 'class' => 'text-danger', 'days' => abs($daysUntil)];
        } elseif ($daysUntil <= 30) {
            return ['status' => 'critical', 'label' => "Expires in {$daysUntil} days", 'class' => 'text-danger', 'days' => $daysUntil];
        } elseif ($daysUntil <= 90) {
            return ['status' => 'warning', 'label' => "Expires in {$daysUntil} days", 'class' => 'text-warning', 'days' => $daysUntil];
        }

        return ['status' => 'ok', 'label' => $batch->expiry_date->format('d M Y'), 'class' => 'text-success', 'days' => $daysUntil];
    }

    /**
     * Get action buttons for stock overview
     */
    protected function getStockActionButtons(StoreStock $ss): string
    {
        $buttons = [];

        // View batches
        $buttons[] = sprintf(
            '<a href="%s" class="btn btn-sm btn-info" title="View Batches"><i class="fas fa-layer-group"></i></a>',
            route('inventory.store-workbench.product-batches', ['product' => $ss->product_id, 'store_id' => $ss->store_id])
        );

        // Add batch
        $buttons[] = sprintf(
            '<a href="%s" class="btn btn-sm btn-success" title="Add Batch"><i class="fas fa-plus"></i></a>',
            route('inventory.store-workbench.manual-batch-form') . '?store_id=' . $ss->store_id . '&product_id=' . $ss->product_id
        );

        // Edit product
        $buttons[] = sprintf(
            '<a href="%s" class="btn btn-sm btn-secondary" title="Edit Product"><i class="fas fa-edit"></i></a>',
            route('products.edit', $ss->product_id)
        );

        // Edit pricing
        $buttons[] = sprintf(
            '<a href="%s" class="btn btn-sm btn-warning" title="Adjust Price"><i class="fas fa-tag"></i></a>',
            route('prices.edit', $ss->product_id)
        );

        return '<div class="btn-group btn-group-sm">' . implode('', $buttons) . '</div>';
    }

    /**
     * Tally Card — page view
     *
     * Axes:
     *  - product: shows every StockBatchTransaction for one product in one store
     *  - store:   shows every StockBatchTransaction for ALL products in one store
     */
    public function tallyCard(Request $request)
    {
        $user = auth()->user();
        $stores = Store::active()->forUser($user)->orderBy('store_name')->get();
        $allStores = Store::active()->orderBy('store_name')->get();

        // Resolve store
        $storeId = $request->get('store_id');
        if (!$storeId) {
            $resolved = $this->contextResolver->resolve($user);
            $storeId = $resolved?->id ?? $stores->first()?->id;
        }
        $selectedStore = $storeId ? Store::find($storeId) : null;

        // Access guard
        if ($selectedStore && !$stores->contains('id', $selectedStore->id)) {
            abort(403, 'You do not have access to this store.');
        }

        $axis = $request->get('axis', 'product');
        $selectedProduct = null;
        if ($axis === 'product' && $request->filled('product_id')) {
            $selectedProduct = Product::find($request->product_id);
        }

        // Products dropdown (for axis=product selector)
        $products = Product::where('status', 1)->orderBy('product_name')->get();

        // Suppliers for PO creation modal
        $suppliers = \App\Models\Supplier::orderBy('company_name')->get();

        // Pending panels — always scoped to selected store
        $pendingIncomingReqs = collect();
        $pendingOutgoingReqs = collect();
        $pendingPOs = collect();

        if ($selectedStore) {
            $pendingIncomingReqs = \App\Models\StoreRequisition::where('from_store_id', $storeId)
                ->whereIn('status', ['pending', 'approved', 'partial'])
                ->with(['items.product.packagings', 'fromStore'])
                ->orderBy('created_at', 'desc')
                ->get();

            $pendingOutgoingReqs = \App\Models\StoreRequisition::where('to_store_id', $storeId)
                ->whereIn('status', ['pending', 'approved', 'partial'])
                ->with(['items.product.packagings', 'toStore'])
                ->orderBy('created_at', 'desc')
                ->get();

            $pendingPOs = \App\Models\PurchaseOrder::where('target_store_id', $storeId)
                ->whereIn('status', ['approved', 'partial', 'partially_received', 'partial_received'])
                ->with(['supplier', 'items.product.packagings', 'targetStore'])
                ->orderBy('created_at', 'desc')
                ->get();

            \Illuminate\Support\Facades\Log::info('Tally Card Debug', [
                'store_id' => $storeId,
                'incoming_count' => $pendingIncomingReqs->count(),
                'outgoing_count' => $pendingOutgoingReqs->count(),
                'po_count' => $pendingPOs->count(),
            ]);
        }

        return view('admin.inventory.store-workbench.tally-card', compact(
            'stores',
            'allStores',
            'selectedStore',
            'products',
            'selectedProduct',
            'suppliers',
            'axis',
            'pendingIncomingReqs',
            'pendingOutgoingReqs',
            'pendingPOs'
        ));
    }

    /**
     * Tally Card — AJAX data feed
     *
     * Returns JSON for the tally table.
     * axis=product: transactions for one product in one store, with single running balance.
     * axis=store:   transactions for all products in one store, with per-product running balance.
     */
    public function tallyCardData(Request $request)
    {
        $request->validate([
            'axis' => 'required|in:product,store',
            'store_id' => 'required|exists:stores,id',
            'product_id' => 'required_if:axis,product|nullable|exists:products,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        // Governance: user must have access to the requested store
        $accessibleStoreIds = Store::active()->forUser(auth()->user())->pluck('id');
        if (!$accessibleStoreIds->contains((int) $request->store_id)) {
            return response()->json(['success' => false, 'message' => 'Access denied to this store.'], 403);
        }

        $storeId = (int) $request->store_id;
        $axis = $request->axis;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        // Note: we intentionally do NOT filter is_active=true — we want full audit history
        // including transactions on batches that were fully depleted (deactivated).
        $query = \App\Models\StockBatchTransaction::whereHas('stockBatch', function ($q) use ($storeId, $axis, $request) {
            $q->where('store_id', $storeId);
            if ($axis === 'product') {
                $q->where('product_id', (int) $request->product_id);
            }
        })
            ->with(['stockBatch.product.packagings', 'stockBatch.store', 'performer'])
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->orderBy('created_at')
            ->orderBy('id');

        $transactions = $query->get();

        // Type classification helpers
        $inboundTypes = ['in', 'transfer_in', 'return', 'req_return'];
        $outboundTypes = ['out', 'transfer_out', 'expired', 'damaged', 'po_return'];

        // Human-readable labels and optional deep-link URLs per reference_type
        $refLabelMap = [
            'PurchaseOrder' => ['prefix' => 'PO #',        'url_route' => 'inventory.purchase-orders.show'],
            'StoreRequisition' => ['prefix' => 'Requisition #', 'url_route' => 'inventory.requisitions.show'],
            'ProductRequest' => ['prefix' => 'Pharmacy Dispense #', 'url_route' => null],
            'ProductOrServiceRequest' => ['prefix' => 'Clinical Bill #',     'url_route' => null],
            'InjectionAdministration' => ['prefix' => 'Injection #',         'url_route' => null],
            'MedicationAdministration' => ['prefix' => 'Med Admin',           'url_route' => null],
            'PharmacyReturn' => ['prefix' => 'Drug Return #',       'url_route' => null],
            'PharmacyDamage' => ['prefix' => 'Damage #',            'url_route' => null],
            'StoreDamage' => ['prefix' => 'Store Damage #',      'url_route' => null],
            'StoreRequisitionReturn' => ['prefix' => 'Req Return #',        'url_route' => null],
            'PurchaseOrderReturn' => ['prefix' => 'PO Return #',         'url_route' => null],
            // Legacy migration rows created during initial data import
            'Migration' => ['prefix' => 'Legacy Import',       'url_route' => null],
        ];

        // Human-readable type labels (covers all 8 types + transfer_in for completeness)
        $typeLabelMap = [
            'in' => 'Stock In',
            'out' => 'Dispensed',
            'transfer_in' => 'Transfer In',
            'transfer_out' => 'Transfer Out',
            'return' => 'Return',
            'expired' => 'Expired',
            'damaged' => 'Damaged',
            'adjustment' => 'Adjustment',
            'po_return' => 'PO Return',
            'req_return' => 'Req Return',
        ];

        // Per-product running balance accumulator (used in both axes)
        $balances = []; // keyed by product_id

        // --- 1. Calculate Opening Balances (Historical sum before date_from) ---
        if ($dateFrom) {
            $openingQuery = \App\Models\StockBatchTransaction::whereHas('stockBatch', function ($q) use ($storeId, $axis, $request) {
                $q->where('store_id', $storeId);
                if ($axis === 'product') {
                    $q->where('product_id', (int) $request->product_id);
                }
            })->where('created_at', '<', $dateFrom);

            // Fetch all historical transactions before the start date and group by product
            $history = $openingQuery->get();
            foreach ($history as $tx) {
                $pid = $tx->stockBatch->product_id;
                $txQty = (int) $tx->qty;

                $isIn = in_array($tx->type, $inboundTypes);
                $isOut = in_array($tx->type, $outboundTypes);

                if ($isIn) {
                    $balances[$pid] = ($balances[$pid] ?? 0) + $txQty;
                } elseif ($isOut) {
                    $balances[$pid] = ($balances[$pid] ?? 0) - $txQty;
                } elseif ($tx->type === 'adjustment') {
                    if (str_starts_with($tx->notes ?? '', 'Positive adjustment')) {
                        $balances[$pid] = ($balances[$pid] ?? 0) + $txQty;
                    } else {
                        $balances[$pid] = ($balances[$pid] ?? 0) - $txQty;
                    }
                }
            }
        }

        // Snapshot opening balances per-product BEFORE processing the period's transactions.
        // For product axis: single entry; for store axis: one per product seen in history.
        $openingBalances = $balances; // keyed by product_id → qty at period start

        $totalIn = 0;
        $totalOut = 0;

        $rows = $transactions->map(function ($tx) use (
            $inboundTypes,
            $outboundTypes,
            $refLabelMap,
            $typeLabelMap,
            &$balances,
            &$totalIn,
            &$totalOut
        ) {
            $productId = $tx->stockBatch->product_id;
            $product = $tx->stockBatch->product;
            $productName = $product?->product_name ?? '—';
            $productCode = $product?->product_code ?? '';
            $batchNumber = $tx->stockBatch->batch_number ?? '—';
            $expiryDate = $tx->stockBatch->expiry_date ? $tx->stockBatch->expiry_date->format('Y-m-d') : '—';

            // Batch cost price with 0 as fallback
            $costPrice = (float) ($tx->stockBatch->cost_price ?? 0);

            $type = $tx->type;
            $txQty = abs((int) $tx->qty);

            $isIn = in_array($type, $inboundTypes);
            $isOut = in_array($type, $outboundTypes);

            $balBefore = $balances[$productId] ?? 0;

            if ($isIn) {
                $balances[$productId] = $balBefore + $txQty;
                $totalIn += $txQty;
                $inQty = $txQty;
                $outQty = 0;
            } elseif ($isOut) {
                $balances[$productId] = $balBefore - $txQty;
                $totalOut += $txQty;
                $inQty = 0;
                $outQty = $txQty;
            } else {
                // Adjustment
                $isPositiveAdj = str_starts_with($tx->notes ?? '', 'Positive adjustment');
                if ($isPositiveAdj) {
                    $balances[$productId] = $balBefore + $txQty;
                    $totalIn += $txQty;
                    $inQty = $txQty;
                    $outQty = 0;
                } else {
                    $balances[$productId] = $balBefore - $txQty;
                    $totalOut += $txQty;
                    $inQty = 0;
                    $outQty = $txQty;
                }
            }

            $balAfter = $balances[$productId];

            // Resolve reference label and optional URL
            $refType = $tx->reference_type;
            $refId = $tx->reference_id;
            $refLabel = '—';
            $refUrl = null;

            if ($refType) {
                $shortType = class_basename($refType);
                $map = $refLabelMap[$shortType] ?? null;

                if ($map) {
                    $refLabel = $refId ? ($map['prefix'] . $refId) : rtrim($map['prefix'], ' #');
                    if ($map['url_route'] && $refId) {
                        try {
                            $refUrl = route($map['url_route'], $refId);
                        } catch (\Exception $e) {
                            $refUrl = null;
                        }
                    }
                } else {
                    $refLabel = $refId ? ($shortType . ' #' . $refId) : $shortType;
                }
            }

            $isVoided = !empty($tx->voided_at);

            // Determine direction and labels
            if ($type === 'in' && ($tx->stockBatch?->is_donation || $refType === 'donation')) {
                $direction = 'donation_in';
                $typeLabel = 'Donation In';
                $badgeType = 'donation_in';
            } elseif ($type === 'in' && class_basename($refType ?? '') === 'StoreRequisition') {
                $direction = 'transfer_in';
                $typeLabel = 'Transfer In';
                $badgeType = 'transfer_in';
            } elseif ($type === 'in' && class_basename($refType ?? '') === 'PurchaseOrder') {
                $direction = 'in';
                $typeLabel = 'PO Receipt';
                $badgeType = 'po_receipt';
            } elseif ($type === 'in') {
                $direction = 'in';
                $typeLabel = $typeLabelMap[$type] ?? 'Stock In';
                $badgeType = 'in';
            } elseif ($type === 'adjustment') {
                $direction = 'adjustment';
                $typeLabel = ($inQty > 0 ? 'Adj (+)' : 'Adj (-)') . ($isVoided ? ' (Voided)' : '');
                $badgeType = $inQty > 0 ? 'adjustment_in' : 'adjustment_out';
            } elseif ($type === 'void' || $type === 'void_adjustment') {
                $direction = 'adjustment';
                $typeLabel = 'Void / Reversal';
                $badgeType = 'adjustment_out';
            } else {
                $direction = $type;
                $typeLabel = ($typeLabelMap[$type] ?? ucwords(str_replace('_', ' ', $type))) . ($isVoided ? ' (Voided)' : '');
                $badgeType = $type;
            }

            return [
                'id' => $tx->id,
                'raw_type' => $type,
                'is_voided' => $isVoided,
                'void_reason' => $tx->void_reason,
                'journal_entry_id' => $tx->journal_entry_id,
                'datetime' => $tx->created_at->format('d M Y H:i'),
                'product_id' => $productId,
                'product_name' => $productName,
                'type_label' => $typeLabel,
                'badge_type' => $badgeType,
                'is_donation' => (bool) ($tx->stockBatch?->is_donation || $refType === 'donation'),
                'direction' => $direction,
                'batch_id' => $tx->stock_batch_id,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'cost_price' => $costPrice,
                'supplier_id' => $tx->stockBatch?->supplier_id,
                'supplier_name' => $tx->stockBatch?->supplier?->company_name,
                'current_qty' => (int) ($tx->stockBatch?->current_qty ?? 0),
                'source' => $tx->stockBatch?->source,
                'bal_before' => $balBefore,
                'in_qty' => $inQty,
                'out_qty' => $outQty,
                'bal_after' => $balAfter,
                'ref_label' => $refLabel,
                'ref_url' => $refUrl,
                'performer' => $tx->performer->name ?? 'System',
                'notes' => $tx->notes,
                'packaging' => ($product->packagings ?? collect())->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name, 'base_unit_qty' => $p->base_unit_qty,
                ]),
                'base_unit' => $product->base_unit_name ?? 'Piece',
            ];
        });

        // Summary
        $singleBalance = count($balances) === 1 ? reset($balances) : null;

        // Re-compute byProduct totals using the same per-row in_qty/out_qty values already in $rows
        // so that adjustments are counted consistently with the main totals.
        $byProductAccum = [];
        foreach ($rows as $row) {
            $pid = $row['product_id'];
            if (!isset($byProductAccum[$pid])) {
                $byProductAccum[$pid] = ['product_name' => $row['product_name'], 'total_in' => 0, 'total_out' => 0];
            }
            $byProductAccum[$pid]['total_in'] += $row['in_qty'];
            $byProductAccum[$pid]['total_out'] += $row['out_qty'];
        }

        $byProduct = collect($balances)->map(function ($bal, $productId) use ($byProductAccum) {
            $accum = $byProductAccum[$productId] ?? ['product_name' => 'Unknown', 'total_in' => 0, 'total_out' => 0];

            return [
                'product_id' => $productId,
                'product_name' => $accum['product_name'],
                'total_in' => $accum['total_in'],
                'total_out' => $accum['total_out'],
                'closing_balance' => $bal,
            ];
        })->values();

        // Current live balance from store_stocks
        $currentStoreStock = null;
        if ($axis === 'product' && $request->filled('product_id')) {
            $ss = \App\Models\StoreStock::where('product_id', $request->product_id)
                ->where('store_id', $storeId)
                ->value('current_quantity');
            $currentStoreStock = $ss ?? 0;
        }

        return response()->json([
            'success' => true,
            'axis' => $axis,
            'transactions' => $rows->values(),
            'summary' => [
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'net_movement' => $totalIn - $totalOut,
                'opening_balance' => $axis === 'product' ? ($openingBalances[(int)$request->product_id] ?? 0) : null,
                'opening_balances' => $openingBalances, // keyed by product_id → qty at period start
                'closing_balance' => $singleBalance,
                'current_store_stock' => $currentStoreStock,
                'products_touched' => count($balances),
                'by_product' => $byProduct,
            ],
        ]);
    }

    /**
     * Tally Card — Pending Actions (AJAX)
     *
     * Returns JSON for the pending requisitions and PO panels.
     */
    public function pendingActions(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
        ]);

        $storeId = (int) $request->store_id;

        // Governance: user must have access to the requested store
        $accessibleStoreIds = Store::active()->forUser(auth()->user())->pluck('id');
        if (!$accessibleStoreIds->contains($storeId)) {
            return response()->json(['success' => false, 'message' => 'Access denied to this store.'], 403);
        }

        $pendingIncomingReqs = StoreRequisition::where('from_store_id', $storeId)
            ->whereIn('status', ['pending', 'approved', 'partial'])
            ->with(['items.product.packagings', 'toStore'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingOutgoingReqs = StoreRequisition::where('to_store_id', $storeId)
            ->whereIn('status', ['pending', 'approved', 'partial'])
            ->with(['items.product.packagings', 'fromStore'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingPOs = PurchaseOrder::where('target_store_id', $storeId)
            ->whereIn('status', ['approved', 'partial', 'partially_received', 'partial_received'])
            ->with(['supplier', 'items.product.packagings', 'targetStore'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingDamages = \App\Models\StoreDamage::where('store_id', $storeId)
            ->where('status', 'pending')
            ->with(['product'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingReqReturns = \App\Models\StoreRequisitionReturn::where(function ($q) use ($storeId) {
            $q->where('source_store_id', $storeId)
              ->orWhere('destination_store_id', $storeId);
        })
            ->where('status', 'pending')
            ->with(['product', 'sourceStore', 'destinationStore'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingPoReturns = \App\Models\PurchaseOrderReturn::where('store_id', $storeId)
            ->where('status', 'pending')
            ->with(['purchaseOrder', 'product'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'counts' => [
                'incoming' => $pendingIncomingReqs->count(),
                'outgoing' => $pendingOutgoingReqs->count(),
                'pos' => $pendingPOs->count(),
                'damages' => $pendingDamages->count(),
                'req_returns' => $pendingReqReturns->count(),
                'po_returns' => $pendingPoReturns->count(),
            ],
            'incoming' => $pendingIncomingReqs,
            'outgoing' => $pendingOutgoingReqs,
            'pos' => $pendingPOs,
            'damages' => $pendingDamages,
            'req_returns' => $pendingReqReturns,
            'po_returns' => $pendingPoReturns,
        ]);
    }

    /**
     * Get batch availability for AJAX
     */
    public function getBatchAvailability(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'store_id' => 'required|exists:stores,id',
        ]);

        $batches = BatchHelper::getBatchSelectOptions(
            $request->product_id,
            $request->store_id
        );

        $totalAvailable = array_sum(array_column($batches, 'qty'));

        return response()->json([
            'success' => true,
            'total_available' => $totalAvailable,
            'batches' => $batches,
        ]);
    }

    /**
     * AJAX: Get active batches for a store (for adjust stock modal).
     * Optional product_id filter.
     */
    public function getStoreBatches(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_id' => 'nullable|exists:products,id',
        ]);

        $query = StockBatch::where('store_id', $request->store_id)
            ->active()
            ->where('current_qty', '>', 0)
            ->with(['product', 'supplier'])
            ->orderBy('expiry_date')
            ->orderBy('created_at');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $batches = $query->get()->map(function ($batch) {
            return [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number ?? 'N/A',
                'product_id' => $batch->product_id,
                'product_name' => $batch->product?->product_name ?? 'Unknown',
                'product_code' => $batch->product?->product_code ?? null,
                'base_unit_name' => $batch->product?->base_unit_name ?? 'Piece',
                'current_qty' => $batch->current_qty,
                'supplier_id' => $batch->supplier_id,
                'supplier_name' => $batch->supplier?->company_name ?? '—',
                'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                'expiry_label' => $batch->expiry_date?->format('M d, Y') ?? 'No expiry',
                'cost_price' => (float) ($batch->cost_price ?? 0),
                'is_expired' => $batch->is_expired ?? false,
                'is_expiring_soon' => $batch->expiry_date && $batch->expiry_date->diffInDays(now()) <= 30,
            ];
        });

        return response()->json([
            'success' => true,
            'batches' => $batches,
        ]);
    }
}
