<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StockBatch;
use App\Models\StoreStock;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StoreRequisition;
use App\Services\StockService;
use App\Services\PurchaseOrderService;
use App\Services\RequisitionService;
use App\Helpers\BatchHelper;
use Illuminate\Http\Request;
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

    public function __construct(
        StockService $stockService,
        PurchaseOrderService $purchaseOrderService,
        RequisitionService $requisitionService
    ) {
        $this->stockService = $stockService;
        $this->purchaseOrderService = $purchaseOrderService;
        $this->requisitionService = $requisitionService;
    }

    /**
     * Store Workbench Dashboard
     */
    public function index(Request $request)
    {
        $storeId = $request->get('store_id');
        $store = $storeId ? Store::find($storeId) : null;
        $stores = Store::active()->orderBy('store_name')->get();

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
            $pendingPOs = $this->purchaseOrderService->getReadyToReceive()
                ->where('target_store_id', $storeId);
            $pendingRequisitions = $this->requisitionService->getPendingFulfillment($storeId);
            $incomingRequisitions = $this->requisitionService->getMyRequisitions($storeId)
                ->whereIn('status', ['approved', 'partial']);

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
            'lowStockItems'
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
                ->addColumn('product_name', fn($ss) => $ss->product->product_name ?? '-')
                ->addColumn('product_code', fn($ss) => $ss->product->product_code ?? '-')
                ->addColumn('category', fn($ss) => $ss->product->category->category_name ?? '-')
                ->addColumn('store_name', fn($ss) => $ss->store->store_name ?? '-')
                ->addColumn('qty_display', fn($ss) => $this->formatQuantityDisplay($ss))
                ->addColumn('batches_count', fn($ss) => StockBatch::where('product_id', $ss->product_id)
                    ->where('store_id', $ss->store_id)
                    ->active()
                    ->hasStock()
                    ->count())
                ->addColumn('actions', fn($ss) => $this->getStockActionButtons($ss))
                ->rawColumns(['qty_display', 'actions'])
                ->make(true);
        }

        $stores = Store::active()->orderBy('store_name')->get();

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
        $stores = Store::active()->orderBy('store_name')->get();

        // Get store filter if specified
        $storeId = $request->get('store_id');
        $selectedStore = $storeId ? Store::find($storeId) : null;

        // Build query for batches
        $batchQuery = StockBatch::where('product_id', $productId)
            ->active()
            ->with(['creator', 'store', 'supplier', 'purchaseOrderItem.purchaseOrder'])
            ->fifoOrder();

        // Filter by store if specified
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

        return view('admin.inventory.store-workbench.product-batches', compact(
            'product',
            'batches',
            'stores',
            'selectedStore',
            'totalStock',
            'totalBatches'
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
            $transaction = $this->stockService->writeOffExpired(
                $batchId,
                $request->qty,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Expired stock written off',
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
     * Write off damaged stock
     */
    public function writeOffDamaged(Request $request, int $batchId)
    {
        $request->validate([
            'qty' => 'required|integer|min:1',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $transaction = $this->stockService->writeOffDamaged(
                $batchId,
                $request->qty,
                $request->reason
            );

            return response()->json([
                'success' => true,
                'message' => 'Damaged stock written off',
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
     * Manual batch entry form
     */
    public function manualBatchForm(Request $request)
    {
        $storeId = $request->get('store_id', Store::getDefaultPharmacy()?->id);
        $store = Store::findOrFail($storeId);
        $stores = Store::active()->orderBy('store_name')->get();
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
            'supplier_id' => 'nullable|exists:suppliers,id',
            'quantity' => 'required|integer|min:1',
            'cost_price' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date|after:today',
            'batch_name' => 'nullable|string|max:100',
            'batch_number' => 'required|string|max:100|unique:stock_batches,batch_number',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $batch = $this->stockService->createBatch([
                'product_id' => $request->product_id,
                'store_id' => $request->store_id,
                'supplier_id' => $request->supplier_id,
                'qty' => $request->quantity,
                'cost_price' => $request->cost_price ?? 0,
                'expiry_date' => $request->expiry_date,
                'batch_name' => $request->batch_name,
                'batch_number' => $request->batch_number,
                'source' => StockBatch::SOURCE_MANUAL,
                'notes' => $request->notes ?? 'Manual entry',
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
            \Log::error('Manual batch creation failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
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
        $stores = Store::active()->orderBy('store_name')->get();
        $store = $storeId ? Store::find($storeId) : null;

        return view('admin.inventory.store-workbench.expiry-report', compact('expiringBatches', 'stores', 'store', 'days'));
    }

    /**
     * Stock value report
     */
    public function stockValueReport(Request $request)
    {
        $storeId = $request->get('store_id');
        $stores = Store::active()->orderBy('store_name')->get();

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

        if ($qty <= 0) {
            return "<span class='badge badge-danger'>Out of Stock</span>";
        } elseif ($qty <= $reorderLevel) {
            return "<span class='badge badge-warning'>{$qty}</span> <small class='text-muted'>(Low)</small>";
        }

        return "<span class='text-success'>{$qty}</span>";
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
}
