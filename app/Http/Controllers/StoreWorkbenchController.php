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
        $storeId = $request->get('store_id', Store::getDefaultPharmacy()?->id);
        $store = Store::findOrFail($storeId);
        $stores = Store::active()->orderBy('store_name')->get();

        // Dashboard statistics
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
     * Stock overview (list all products with stock in store)
     */
    public function stockOverview(Request $request)
    {
        $storeId = $request->get('store_id', Store::getDefaultPharmacy()?->id);
        $store = Store::findOrFail($storeId);

        if ($request->ajax()) {
            $query = StoreStock::with(['product.category', 'store'])
                ->where('store_id', $storeId)
                ->where('is_active', true);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('product_name', 'like', "%{$search}%")
                      ->orWhere('product_code', 'like', "%{$search}%");
                });
            }

            if ($request->filled('low_stock_only') && $request->low_stock_only) {
                $query->whereRaw('qty <= reorder_level');
            }

            return DataTables::of($query)
                ->addColumn('product_name', fn($ss) => $ss->product->product_name ?? '-')
                ->addColumn('product_code', fn($ss) => $ss->product->product_code ?? '-')
                ->addColumn('category', fn($ss) => $ss->product->category->category_name ?? '-')
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

        return view('admin.inventory.store-workbench.stock-overview', compact('store', 'stores'));
    }

    /**
     * View batches for a specific product in store
     */
    public function productBatches(Request $request, int $productId)
    {
        $storeId = $request->get('store_id', Store::getDefaultPharmacy()?->id);
        $store = Store::findOrFail($storeId);
        $product = Product::findOrFail($productId);

        $batches = StockBatch::where('product_id', $productId)
            ->where('store_id', $storeId)
            ->active()
            ->with(['creator', 'purchaseOrderItem.purchaseOrder'])
            ->fifoOrder()
            ->get();

        // Add expiry info to each batch
        $batches = $batches->map(function ($batch) {
            $batch->expiry_status = $this->getExpiryStatus($batch);
            return $batch;
        });

        return view('admin.inventory.store-workbench.product-batches', compact('store', 'product', 'batches'));
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
        $products = Product::where('visible', true)->orderBy('product_name')->get();

        return view('admin.inventory.store-workbench.manual-batch-form', compact('store', 'products'));
    }

    /**
     * Create manual batch entry
     */
    public function createManualBatch(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'cost_price' => 'required|numeric|min:0',
            'expiry_date' => 'nullable|date|after:today',
            'batch_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $batch = $this->stockService->createBatch([
                'product_id' => $request->product_id,
                'store_id' => $request->store_id,
                'qty' => $request->qty,
                'cost_price' => $request->cost_price,
                'expiry_date' => $request->expiry_date,
                'batch_name' => $request->batch_name,
                'source' => StockBatch::SOURCE_MANUAL,
                'notes' => $request->notes ?? 'Manual entry',
            ]);

            return response()->json([
                'success' => true,
                'message' => "Batch {$batch->batch_number} created successfully",
                'batch' => $batch,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create batch: ' . $e->getMessage(),
            ], 500);
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
        $storeId = $request->get('store_id', Store::getDefaultPharmacy()?->id);
        $store = Store::findOrFail($storeId);
        $stores = Store::active()->orderBy('store_name')->get();

        $report = $this->stockService->getStockValueReport($storeId);

        return view('admin.inventory.store-workbench.stock-value-report', compact('store', 'stores', 'report'));
    }

    /**
     * Get store statistics
     */
    protected function getStoreStats(int $storeId): array
    {
        $totalProducts = StoreStock::where('store_id', $storeId)
            ->where('is_active', true)
            ->where('qty', '>', 0)
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
            ->whereRaw('qty <= reorder_level')
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
        $qty = $ss->qty;
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
            route('store-workbench.product-batches', ['product' => $ss->product_id, 'store_id' => $ss->store_id])
        );

        // Create requisition
        $buttons[] = sprintf(
            '<button class="btn btn-sm btn-primary btn-request-stock" data-product-id="%d" title="Request Stock"><i class="fas fa-truck"></i></button>',
            $ss->product_id
        );

        return '<div class="btn-group">' . implode('', $buttons) . '</div>';
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
