<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPackaging;
use App\Models\StockBatch;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PharmacyReportsController extends Controller
{
    /**
     * Display stock reports dashboard or return stats for AJAX
     */
    public function index(Request $request)
    {
        if ($request->ajax() && $request->has('stats_only')) {
            $products = StoreStock::distinct('product_id')->count('product_id');
            $totalValue = StoreStock::join('stores', 'store_stocks.store_id', '=', 'stores.id')
                ->join('prices', 'store_stocks.product_id', '=', 'prices.product_id')
                ->where('stores.store_type', 'pharmacy')
                ->sum(DB::raw('store_stocks.current_quantity * prices.pr_buy_price'));
            $lowStock = StoreStock::join('stores', 'store_stocks.store_id', '=', 'stores.id')
                ->where('stores.store_type', 'pharmacy')
                ->whereColumn('store_stocks.current_quantity', '<=', 'store_stocks.reorder_level')
                ->where('store_stocks.current_quantity', '>', 0)
                ->count();
            $outOfStock = StoreStock::join('stores', 'store_stocks.store_id', '=', 'stores.id')
                ->where('stores.store_type', 'pharmacy')
                ->where('store_stocks.current_quantity', '<=', 0)
                ->count();

            return response()->json([
                'stats' => [
                    'products' => $products,
                    'total_value' => $totalValue,
                    'low_stock' => $lowStock,
                    'out_of_stock' => $outOfStock,
                ],
            ]);
        }

        $stores = Store::where('store_type', 'pharmacy')
            ->orderBy('store_name')
            ->get();

        $categories = ProductCategory::orderBy('category_name')->get();

        return view('admin.pharmacy.reports.index', compact('stores', 'categories'));
    }

    /**
     * Generate comprehensive stock report (DataTables)
     */
    public function stockReport(Request $request)
    {
        $query = StoreStock::select(
            'store_stocks.id',
            'store_stocks.product_id',
            'store_stocks.store_id',
            'store_stocks.current_quantity',
            'store_stocks.reorder_level',
            'prices.pr_buy_price as unit_cost',
            'products.product_name',
            'products.product_code',
            'products.category_id',
            'product_categories.category_name',
            'stores.store_name',
            DB::raw('(store_stocks.current_quantity * prices.pr_buy_price) as total_value')
        )
            ->join('products', 'store_stocks.product_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->join('stores', 'store_stocks.store_id', '=', 'stores.id')
            ->leftJoin('prices', 'store_stocks.product_id', '=', 'prices.product_id')
            ->where('stores.store_type', 'pharmacy');

        // Filter by store
        if ($request->filled('store_id')) {
            $query->where('store_stocks.store_id', $request->store_id);
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }

        // Filter by stock level
        if ($request->filled('stock_level')) {
            switch ($request->stock_level) {
                case 'low':
                    $query->whereRaw('store_stocks.current_quantity <= store_stocks.reorder_level');

                    break;
                case 'out':
                    $query->where('store_stocks.current_quantity', '<=', 0);

                    break;
                case 'available':
                    $query->where('store_stocks.current_quantity', '>', 0);

                    break;
            }
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('stock_status', function ($stock) {
                if ($stock->current_quantity <= 0) {
                    return '<span class="badge badge-danger">Out of Stock</span>';
                } elseif ($stock->current_quantity <= $stock->reorder_level) {
                    return '<span class="badge badge-warning">Low Stock</span>';
                } else {
                    return '<span class="badge badge-success">In Stock</span>';
                }
            })
            ->addColumn('total_value_formatted', function ($stock) {
                return number_format($stock->total_value, 2);
            })
            ->rawColumns(['stock_status'])
            ->make(true);
    }

    /**
     * Generate stock summary by store
     */
    public function stockByStore(Request $request)
    {
        $storeSummary = StoreStock::select(
            'stores.id',
            'stores.store_name',
            DB::raw('COUNT(DISTINCT store_stocks.product_id) as total_products'),
            DB::raw('SUM(store_stocks.current_quantity) as total_quantity'),
            DB::raw('SUM(store_stocks.current_quantity * COALESCE(prices.pr_buy_price, 0)) as total_value'),
            DB::raw('COUNT(CASE WHEN store_stocks.current_quantity <= 0 THEN 1 END) as out_of_stock_count'),
            DB::raw('COUNT(CASE WHEN store_stocks.current_quantity <= store_stocks.reorder_level AND store_stocks.current_quantity > 0 THEN 1 END) as low_stock_count')
        )
            ->join('stores', 'store_stocks.store_id', '=', 'stores.id')
            ->leftJoin('prices', 'store_stocks.product_id', '=', 'prices.product_id')
            ->where('stores.store_type', 'pharmacy')
            ->groupBy('stores.id', 'stores.store_name')
            ->orderBy('stores.store_name')
            ->get();

        return response()->json($storeSummary);
    }

    /**
     * Generate stock summary by category
     */
    public function stockByCategory(Request $request)
    {
        $storeId = $request->get('store_id');

        $query = StoreStock::select(
            'product_categories.id',
            'product_categories.category_name',
            DB::raw('COUNT(DISTINCT store_stocks.product_id) as total_products'),
            DB::raw('SUM(store_stocks.current_quantity) as total_quantity'),
            DB::raw('SUM(store_stocks.current_quantity * COALESCE(prices.pr_buy_price, 0)) as total_value')
        )
            ->join('products', 'store_stocks.product_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->join('stores', 'store_stocks.store_id', '=', 'stores.id')
            ->leftJoin('prices', 'store_stocks.product_id', '=', 'prices.product_id')
            ->where('stores.store_type', 'pharmacy');

        if ($storeId) {
            $query->where('store_stocks.store_id', $storeId);
        }

        $categorySummary = $query->groupBy('product_categories.id', 'product_categories.category_name')
            ->orderBy('total_value', 'desc')
            ->get();

        return response()->json($categorySummary);
    }

    /**
     * Generate stock valuation report
     */
    public function valuationReport(Request $request)
    {
        $storeId = $request->get('store_id');

        $query = StoreStock::select(
            'products.product_name',
            'products.product_code',
            'product_categories.category_name',
            'stores.store_name',
            'store_stocks.current_quantity',
            'prices.pr_buy_price as unit_cost',
            DB::raw('(store_stocks.current_quantity * prices.pr_buy_price) as total_value')
        )
            ->join('products', 'store_stocks.product_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->join('stores', 'store_stocks.store_id', '=', 'stores.id')
            ->leftJoin('prices', 'store_stocks.product_id', '=', 'prices.product_id')
            ->where('stores.store_type', 'pharmacy')
            ->where('store_stocks.current_quantity', '>', 0);

        if ($storeId) {
            $query->where('store_stocks.store_id', $storeId);
        }

        $valuationData = $query->orderBy('total_value', 'desc')->get();

        $totalValuation = $valuationData->sum('total_value');

        return response()->json([
            'items' => $valuationData,
            'total_valuation' => $totalValuation,
            'total_items' => $valuationData->count(),
        ]);
    }

    /**
     * Compute packaging hierarchy string and bulk breakdown for a product item
     */
    protected function computePackagingInfo($productOrItem, $packagings, float $quantity): array
    {
        $baseUnit = $productOrItem->base_unit_name ?: 'Units';
        $hierarchyParts = [];

        if ($packagings->isNotEmpty()) {
            foreach ($packagings as $pkg) {
                $qtyStr = number_format($pkg->base_unit_qty, $pkg->base_unit_qty == intval($pkg->base_unit_qty) ? 0 : 2);
                $hierarchyParts[] = "1 {$pkg->name} = {$qtyStr} {$baseUnit}";
            }
        } elseif (!empty($productOrItem->howmany_to) && $productOrItem->howmany_to > 1) {
            $hierarchyParts[] = "1 Pack = " . number_format($productOrItem->howmany_to) . " {$baseUnit}";
        }

        $hierarchyStr = count($hierarchyParts) > 0 ? implode('; ', $hierarchyParts) : "Base: {$baseUnit}";

        // Bulk packaging breakdown
        $bulkBreakdown = '';
        $defaultBulkPack = $packagings->where('is_default_purchase', true)->first()
            ?: $packagings->where('base_unit_qty', '>', 1)->sortByDesc('base_unit_qty')->first();

        if ($defaultBulkPack && $defaultBulkPack->base_unit_qty > 1) {
            $baseQty = (float) $defaultBulkPack->base_unit_qty;
            $packQty = floor($quantity / $baseQty);
            $remainder = fmod($quantity, $baseQty);
            $pName = $defaultBulkPack->name;
            $pNamePlural = str_ends_with($pName, 's') ? $pName : $pName . 's';
            $packStr = "{$packQty} " . ($packQty == 1 ? $pName : $pNamePlural);
            if ($remainder > 0) {
                $bulkBreakdown = $packQty > 0 ? "{$packStr} & {$remainder} {$baseUnit}" : "{$remainder} {$baseUnit}";
            } else {
                $bulkBreakdown = $packStr;
            }
        } elseif (!empty($productOrItem->howmany_to) && $productOrItem->howmany_to > 1) {
            $baseQty = (float) $productOrItem->howmany_to;
            $packQty = floor($quantity / $baseQty);
            $remainder = fmod($quantity, $baseQty);
            $packStr = "{$packQty} " . ($packQty == 1 ? 'Pack' : 'Packs');
            if ($remainder > 0) {
                $bulkBreakdown = $packQty > 0 ? "{$packStr} & {$remainder} {$baseUnit}" : "{$remainder} {$baseUnit}";
            } else {
                $bulkBreakdown = $packStr;
            }
        }

        return [
            'base_unit' => $baseUnit,
            'packaging_hierarchy' => $hierarchyStr,
            'bulk_breakdown' => $bulkBreakdown,
        ];
    }

    /**
     * Format person name strictly including othername if available
     */
    protected function formatPersonName(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        $parts = array_filter([
            trim($user->surname ?? ''),
            trim($user->firstname ?? ''),
            trim($user->othername ?? ''),
        ]);

        return count($parts) > 0 ? implode(' ', $parts) : null;
    }

    /**
     * Format a timestamp into standard audit date and time format
     */
    protected function formatAuditDateTime($date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            $c = Carbon::parse($date);

            return $c->format('H:i:s') === '00:00:00'
                ? $c->format('d-M-Y')
                : $c->format('d-M-Y h:i A');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Enrich a stock batch with calculated packaging breakdown and chain-of-custody metadata with datetimes
     */
    protected function enrichBatchDetails($batch, $productOrRow, $packagings): void
    {
        // 1. Packaging breakdown for this batch's current quantity
        $batchPkg = $this->computePackagingInfo($productOrRow, $packagings, (float) $batch->current_qty);
        $batch->pack_breakdown = $batchPkg['bulk_breakdown'] ?? '';

        // 2. Chain of custody details with timestamps
        $collectedBy = null;
        $collectedAt = null;
        $approvedBy = null;
        $approvedAt = null;
        $fulfilledBy = null;
        $fulfilledAt = null;
        $sourceOrigin = null;
        $sourceDate = null;

        if ($batch->sourceRequisition) {
            $req = $batch->sourceRequisition;
            $collectedBy = $this->formatPersonName($req->requester);
            $collectedAt = $this->formatAuditDateTime($req->created_at);

            $approvedBy = $this->formatPersonName($req->approver);
            $approvedAt = $this->formatAuditDateTime($req->approved_at);

            $fulfilledBy = $this->formatPersonName($req->fulfiller);
            $fulfilledAt = $this->formatAuditDateTime($req->fulfilled_at);

            $fromStoreName = $req->fromStore ? $req->fromStore->store_name : null;
            $sourceOrigin = $fromStoreName
                ? ($req->requisition_number ? "{$fromStoreName} ({$req->requisition_number})" : $fromStoreName)
                : ($req->requisition_number ?: 'Requisition Transfer');
            $sourceDate = $this->formatAuditDateTime($req->fulfilled_at ?? $req->created_at);
        }

        if (!$collectedBy && $batch->creator) {
            $collectedBy = $this->formatPersonName($batch->creator);
            $collectedAt = $this->formatAuditDateTime($batch->created_at ?? $batch->received_date);
        } elseif (!$collectedAt && $batch->received_date) {
            $collectedAt = $this->formatAuditDateTime($batch->received_date);
        }

        if (!$approvedBy && $batch->purchaseOrderItem?->purchaseOrder?->approver) {
            $approvedBy = $this->formatPersonName($batch->purchaseOrderItem->purchaseOrder->approver);
            $approvedAt = $this->formatAuditDateTime($batch->purchaseOrderItem->purchaseOrder->approved_at);
        }

        if (!$fulfilledBy && $batch->purchaseOrderItem?->purchaseOrder?->creator) {
            $fulfilledBy = $this->formatPersonName($batch->purchaseOrderItem->purchaseOrder->creator);
            $fulfilledAt = $this->formatAuditDateTime($batch->purchaseOrderItem->received_at ?? $batch->purchaseOrderItem->purchaseOrder->created_at);
        }

        if (!$sourceOrigin) {
            if ($batch->supplier) {
                $sourceOrigin = $batch->supplier->company_name;
                $sourceDate = $this->formatAuditDateTime($batch->received_date ?? $batch->created_at);
            } elseif ($batch->purchaseOrderItem?->purchaseOrder?->supplier) {
                $po = $batch->purchaseOrderItem->purchaseOrder;
                $supplierName = $po->supplier->company_name;
                $sourceOrigin = $po->po_number ? "{$supplierName} ({$po->po_number})" : $supplierName;
                $sourceDate = $this->formatAuditDateTime($batch->purchaseOrderItem->received_at ?? $po->created_at);
            } elseif (!empty($batch->source)) {
                $sourceOrigin = ucwords(str_replace('_', ' ', $batch->source));
                $sourceDate = $this->formatAuditDateTime($batch->received_date ?? $batch->created_at);
            }
        }

        $batch->collected_by_name = $collectedBy;
        $batch->collected_at = $collectedAt;
        $batch->approved_by_name = $approvedBy;
        $batch->approved_at = $approvedAt;
        $batch->fulfilled_by_name = $fulfilledBy;
        $batch->fulfilled_at = $fulfilledAt;
        $batch->source_origin = $sourceOrigin;
        $batch->source_date = $sourceDate;
    }

    /**
     * Export stock report to Excel/CSV with packaging levels and physical verification columns
     */
    public function exportStock(Request $request)
    {
        $query = StoreStock::select(
            'store_stocks.product_id',
            'store_stocks.store_id',
            'stores.store_name',
            'products.product_name',
            'products.product_code',
            'products.base_unit_name',
            'products.howmany_to',
            'product_categories.category_name',
            'store_stocks.current_quantity',
            'store_stocks.reorder_level',
            'prices.pr_buy_price as unit_cost',
            DB::raw('(store_stocks.current_quantity * COALESCE(prices.pr_buy_price, 0)) as total_value')
        )
            ->join('products', 'store_stocks.product_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->join('stores', 'store_stocks.store_id', '=', 'stores.id')
            ->leftJoin('prices', 'store_stocks.product_id', '=', 'prices.product_id')
            ->where('store_stocks.is_active', true);

        if ($request->filled('store_id')) {
            $query->where('store_stocks.store_id', $request->store_id);
        } else {
            $query->where(function ($q) {
                $q->where('stores.store_type', 'pharmacy')
                    ->orWhere('stores.distribution_role', 'like', '%pharmacy%')
                    ->orWhere('stores.store_name', 'like', '%pharmacy%')
                    ->orWhere('stores.allows_direct_patient_dispense', true);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }

        if ($request->filled('stock_level') && $request->stock_level !== 'all') {
            switch ($request->stock_level) {
                case 'low':
                    $query->whereRaw('store_stocks.current_quantity <= IFNULL(NULLIF(store_stocks.reorder_level, 0), products.reorder_alert)');

                    break;
                case 'out':
                    $query->where('store_stocks.current_quantity', '<=', 0);

                    break;
                case 'available':
                case 'in_stock':
                    $query->where('store_stocks.current_quantity', '>', 0);

                    break;
                case 'expiring_soon':
                    $query->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('stock_batches')
                            ->whereColumn('stock_batches.product_id', 'store_stocks.product_id')
                            ->whereColumn('stock_batches.store_id', 'store_stocks.store_id')
                            ->where('stock_batches.current_qty', '>', 0)
                            ->where('stock_batches.is_active', true)
                            ->where('stock_batches.expiry_date', '<=', Carbon::now()->addMonths(3))
                            ->where('stock_batches.expiry_date', '>=', Carbon::now());
                    });

                    break;
                case 'expired':
                    $query->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('stock_batches')
                            ->whereColumn('stock_batches.product_id', 'store_stocks.product_id')
                            ->whereColumn('stock_batches.store_id', 'store_stocks.store_id')
                            ->where('stock_batches.current_qty', '>', 0)
                            ->where('stock_batches.is_active', true)
                            ->where('stock_batches.expiry_date', '<', Carbon::now());
                    });

                    break;
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('products.product_name', 'like', "%{$search}%")
                    ->orWhere('products.product_code', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('stores.store_name')
            ->orderBy('products.product_name')
            ->get();

        $productIds = $data->pluck('product_id')->unique()->values()->all();

        $packagingsMap = ProductPackaging::whereIn('product_id', $productIds)
            ->orderBy('level', 'asc')
            ->get()
            ->groupBy('product_id');

        $batchesQuery = StockBatch::whereIn('product_id', $productIds)
            ->where('current_qty', '>', 0)
            ->active();

        if ($request->filled('store_id')) {
            $batchesQuery->where('store_id', $request->store_id);
        } else {
            $batchesQuery->whereIn('store_id', $data->pluck('store_id')->unique()->values()->all());
        }

        $batches = $batchesQuery
            ->with([
                'creator',
                'supplier',
                'sourceRequisition.requester',
                'sourceRequisition.approver',
                'sourceRequisition.fulfiller',
                'sourceRequisition.fromStore',
                'purchaseOrderItem.purchaseOrder.supplier',
                'purchaseOrderItem.purchaseOrder.approver',
                'purchaseOrderItem.purchaseOrder.creator',
            ])
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date', 'asc')
            ->get();

        $batchMap = $batches->groupBy(function ($b) {
            return $b->store_id . '_' . $b->product_id;
        });

        // Generate CSV
        $filename = 'pharmacy_physical_stock_audit_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($data, $packagingsMap, $batchMap) {
            $file = fopen('php://output', 'w');

            // Add CSV headers with explicit physical verification and packaging levels
            fputcsv($file, [
                'Store Name',
                'Product Code',
                'Product Name',
                'Category',
                'Base Unit',
                'Packaging Levels & Hierarchy',
                'EMR System Qty (Base Units)',
                'Bulk Pack Equiv',
                'Active Batches & Expiry Dates',
                'Reorder Level',
                'Unit Cost (NGN)',
                'Total Valuation (NGN)',
                'Physical Verification Count (Packs / Units)',
                'Physical Count (Total Base Units)',
                'Variance (+/-)',
                'Verification Status (Match/Surplus/Deficit/Damaged)',
                'Remarks / Audit Notes',
            ]);

            // Add data rows
            foreach ($data as $row) {
                $pkgs = $packagingsMap->get($row->product_id, collect());
                $pkgInfo = $this->computePackagingInfo($row, $pkgs, (float) $row->current_quantity);

                $key = $row->store_id . '_' . $row->product_id;
                $rowBatches = $batchMap->get($key, collect());
                $batchParts = [];
                foreach ($rowBatches as $b) {
                    $this->enrichBatchDetails($b, $row, $pkgs);
                    $exp = $b->expiry_date ? date('d-M-Y', strtotime($b->expiry_date)) : 'No Exp';
                    $qtyStr = "Qty: {$b->current_qty}";
                    if (!empty($b->pack_breakdown)) {
                        $qtyStr .= " ({$b->pack_breakdown})";
                    }
                    $part = "{$b->batch_number} [{$qtyStr}, Exp: {$exp}";
                    if ($b->collected_by_name) {
                        $part .= ", Collected: {$b->collected_by_name}" . ($b->collected_at ? " ({$b->collected_at})" : '');
                    }
                    if ($b->approved_by_name) {
                        $part .= ", Approved: {$b->approved_by_name}" . ($b->approved_at ? " ({$b->approved_at})" : '');
                    }
                    if ($b->fulfilled_by_name) {
                        $part .= ", Fulfilled: {$b->fulfilled_by_name}" . ($b->fulfilled_at ? " ({$b->fulfilled_at})" : '');
                    }
                    if ($b->source_origin) {
                        $part .= ", Source: {$b->source_origin}" . ($b->source_date ? " ({$b->source_date})" : '');
                    }
                    $part .= "]";
                    $batchParts[] = $part;
                }
                $batchStr = count($batchParts) > 0 ? implode('; ', $batchParts) : 'None';

                fputcsv($file, [
                    $row->store_name,
                    $row->product_code ?? '',
                    $row->product_name,
                    $row->category_name ?? 'Uncategorized',
                    $pkgInfo['base_unit'],
                    $pkgInfo['packaging_hierarchy'],
                    $row->current_quantity,
                    $pkgInfo['bulk_breakdown'],
                    $batchStr,
                    $row->reorder_level,
                    number_format($row->unit_cost ?? 0, 2, '.', ''),
                    number_format($row->total_value ?? 0, 2, '.', ''),
                    '', // Physical Count write-in (Packs / Units)
                    '', // Physical Count Total Base
                    '', // Variance
                    '', // Status
                    '', // Remarks
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Generate printable physical stock count and audit sheet for Pharmacy
     */
    public function printStock(Request $request)
    {
        $query = StoreStock::select(
            'store_stocks.id',
            'store_stocks.product_id',
            'store_stocks.store_id',
            'store_stocks.current_quantity',
            'store_stocks.reorder_level',
            'prices.pr_buy_price as unit_cost',
            'products.product_name',
            'products.product_code',
            'products.category_id',
            'products.base_unit_name',
            'products.has_have',
            'products.has_piece',
            'products.howmany_to',
            'products.reorder_alert',
            'product_categories.category_name',
            'stores.store_name',
            'stores.code as store_code',
            DB::raw('(store_stocks.current_quantity * COALESCE(prices.pr_buy_price, 0)) as total_value')
        )
            ->join('products', 'store_stocks.product_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->join('stores', 'store_stocks.store_id', '=', 'stores.id')
            ->leftJoin('prices', 'store_stocks.product_id', '=', 'prices.product_id')
            ->where('store_stocks.is_active', true);

        if ($request->filled('store_id')) {
            $query->where('store_stocks.store_id', $request->store_id);
        } else {
            $query->where(function ($q) {
                $q->where('stores.store_type', 'pharmacy')
                    ->orWhere('stores.distribution_role', 'like', '%pharmacy%')
                    ->orWhere('stores.store_name', 'like', '%pharmacy%')
                    ->orWhere('stores.allows_direct_patient_dispense', true);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }

        if ($request->filled('stock_level') && $request->stock_level !== 'all') {
            switch ($request->stock_level) {
                case 'low':
                    $query->whereRaw('store_stocks.current_quantity <= IFNULL(NULLIF(store_stocks.reorder_level, 0), products.reorder_alert)');

                    break;
                case 'out':
                    $query->where('store_stocks.current_quantity', '<=', 0);

                    break;
                case 'available':
                case 'in_stock':
                    $query->where('store_stocks.current_quantity', '>', 0);

                    break;
                case 'expiring_soon':
                    $query->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('stock_batches')
                            ->whereColumn('stock_batches.product_id', 'store_stocks.product_id')
                            ->whereColumn('stock_batches.store_id', 'store_stocks.store_id')
                            ->where('stock_batches.current_qty', '>', 0)
                            ->where('stock_batches.is_active', true)
                            ->where('stock_batches.expiry_date', '<=', Carbon::now()->addMonths(3))
                            ->where('stock_batches.expiry_date', '>=', Carbon::now());
                    });

                    break;
                case 'expired':
                    $query->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('stock_batches')
                            ->whereColumn('stock_batches.product_id', 'store_stocks.product_id')
                            ->whereColumn('stock_batches.store_id', 'store_stocks.store_id')
                            ->where('stock_batches.current_qty', '>', 0)
                            ->where('stock_batches.is_active', true)
                            ->where('stock_batches.expiry_date', '<', Carbon::now());
                    });

                    break;
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('products.product_name', 'like', "%{$search}%")
                    ->orWhere('products.product_code', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('product_categories.category_name')
            ->orderBy('products.product_name')
            ->get();

        // Load active stock batches and packagings for these items
        $productIds = $items->pluck('product_id')->unique()->values()->all();
        $storeIds = $items->pluck('store_id')->unique()->values()->all();

        $batchesQuery = StockBatch::whereIn('product_id', $productIds)
            ->where('current_qty', '>', 0)
            ->active();

        if (count($storeIds) === 1) {
            $batchesQuery->where('store_id', $storeIds[0]);
        } elseif ($request->filled('store_id')) {
            $batchesQuery->where('store_id', $request->store_id);
        } else {
            $batchesQuery->whereIn('store_id', $storeIds);
        }

        $batches = $batchesQuery
            ->with([
                'creator',
                'supplier',
                'sourceRequisition.requester',
                'sourceRequisition.approver',
                'sourceRequisition.fulfiller',
                'sourceRequisition.fromStore',
                'purchaseOrderItem.purchaseOrder.supplier',
                'purchaseOrderItem.purchaseOrder.approver',
                'purchaseOrderItem.purchaseOrder.creator',
            ])
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date', 'asc')
            ->get();

        $batchMap = $batches->groupBy(function ($b) {
            return $b->store_id . '_' . $b->product_id;
        });

        $packagingsMap = ProductPackaging::whereIn('product_id', $productIds)
            ->orderBy('level', 'asc')
            ->get()
            ->groupBy('product_id');

        foreach ($items as $item) {
            $key = $item->store_id . '_' . $item->product_id;
            $itemPackagings = $packagingsMap->get($item->product_id, collect());
            $itemBatches = $batchMap->get($key, collect());

            foreach ($itemBatches as $b) {
                $this->enrichBatchDetails($b, $item, $itemPackagings);
            }

            $item->batches = $itemBatches;
            $item->packagings = $itemPackagings;

            $pkgInfo = $this->computePackagingInfo($item, $item->packagings, (float) $item->current_quantity);
            $item->base_unit = $pkgInfo['base_unit'];
            $item->packaging_hierarchy = $pkgInfo['packaging_hierarchy'];
            $item->bulk_breakdown = $pkgInfo['bulk_breakdown'];
        }

        $storeName = 'All Pharmacy Stores';
        $storeCode = '';
        if ($request->filled('store_id')) {
            $st = Store::find($request->store_id);
            if ($st) {
                $storeName = $st->store_name;
                $storeCode = $st->code;
            }
        }

        $categoryName = 'All Categories';
        if ($request->filled('category_id')) {
            $cat = ProductCategory::find($request->category_id);
            if ($cat) {
                $categoryName = $cat->category_name;
            }
        }

        $stockLevelLabel = 'All Stock';
        if ($request->filled('stock_level') && $request->stock_level !== 'all') {
            $stockLevelLabel = ucwords(str_replace('_', ' ', $request->stock_level));
        }

        $user = auth()->user();
        $printedBy = $user ? ($this->formatPersonName($user) ?? 'System') : 'System';

        $totalProducts = $items->count();
        $totalQuantity = $items->sum('current_quantity');
        $totalValuation = $items->sum('total_value');

        return view('admin.pharmacy.reports.stock_print', compact(
            'items',
            'storeName',
            'storeCode',
            'categoryName',
            'stockLevelLabel',
            'totalProducts',
            'totalQuantity',
            'totalValuation',
            'printedBy'
        ));
    }

    /**
     * Get expiring stock (batches expiring soon)
     */
    public function expiringStock(Request $request)
    {
        $daysAhead = $request->get('days', 90); // Default 90 days
        $storeId = $request->get('store_id');

        $query = StockBatch::select(
            'stock_batches.id',
            'stock_batches.batch_number',
            'stock_batches.expiry_date',
            'stock_batches.current_qty as quantity_available',
            'stock_batches.cost_price as unit_cost',
            'products.product_name',
            'products.product_code',
            'stores.store_name',
            DB::raw('(stock_batches.current_qty * stock_batches.cost_price) as total_value'),
            DB::raw('DATEDIFF(stock_batches.expiry_date, CURDATE()) as days_to_expiry')
        )
            ->join('products', 'stock_batches.product_id', '=', 'products.id')
            ->join('stores', 'stock_batches.store_id', '=', 'stores.id')
            ->where('stores.store_type', 'pharmacy')
            ->where('stock_batches.current_qty', '>', 0)
            ->whereRaw('stock_batches.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ' . (int)$daysAhead . ' DAY)')
            ->whereRaw('stock_batches.expiry_date >= CURDATE()');

        if ($storeId) {
            $query->where('stock_batches.store_id', $storeId);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('expiry_status', function ($batch) {
                $daysToExpiry = $batch->days_to_expiry;
                if ($daysToExpiry <= 30) {
                    return '<span class="badge badge-danger">Expiring Soon (' . $daysToExpiry . ' days)</span>';
                } elseif ($daysToExpiry <= 60) {
                    return '<span class="badge badge-warning">Expiring (' . $daysToExpiry . ' days)</span>';
                } else {
                    return '<span class="badge badge-info">Expiring (' . $daysToExpiry . ' days)</span>';
                }
            })
            ->addColumn('total_value_formatted', function ($batch) {
                return number_format($batch->total_value, 2);
            })
            ->rawColumns(['expiry_status'])
            ->make(true);
    }

    /**
     * Get movement analysis (fast/slow moving items)
     */
    public function movementAnalysis(Request $request)
    {
        $storeId = $request->get('store_id');
        $days = $request->get('days', 30); // Default last 30 days

        // This would require a product_movements or transaction tracking table
        // For now, return a placeholder response
        return response()->json([
            'message' => 'Movement analysis requires transaction history tracking',
            'note' => 'This feature will be implemented when product movement tracking is available',
        ]);
    }
}
