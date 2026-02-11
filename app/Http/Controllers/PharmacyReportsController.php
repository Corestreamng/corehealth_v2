<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\StoreStock;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\StockBatch;
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
                ]
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
            'total_items' => $valuationData->count()
        ]);
    }

    /**
     * Export stock report to Excel/CSV
     */
    public function exportStock(Request $request)
    {
        $query = StoreStock::select(
                'stores.store_name',
                'products.product_name',
                'products.product_code',
                'product_categories.category_name',
                'store_stocks.current_quantity',
                'store_stocks.reorder_level',
                'prices.pr_buy_price as unit_cost',
                DB::raw('(store_stocks.current_quantity * prices.pr_buy_price) as total_value')
            )
            ->join('products', 'store_stocks.product_id', '=', 'products.id')
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->join('stores', 'store_stocks.store_id', '=', 'stores.id')
            ->leftJoin('prices', 'store_stocks.product_id', '=', 'prices.product_id')
            ->where('stores.store_type', 'pharmacy');

        // Apply same filters as stockReport
        if ($request->filled('store_id')) {
            $query->where('store_stocks.store_id', $request->store_id);
        }

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }

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

        $data = $query->orderBy('stores.store_name')
            ->orderBy('products.product_name')
            ->get();

        // Generate CSV
        $filename = 'pharmacy_stock_report_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');

            // Add CSV headers
            fputcsv($file, [
                'Store',
                'Product Name',
                'Product Code',
                'Category',
                'Current Quantity',
                'Reorder Level',
                'Unit Cost',
                'Total Value'
            ]);

            // Add data rows
            foreach ($data as $row) {
                fputcsv($file, [
                    $row->store_name,
                    $row->product_name,
                    $row->product_code,
                    $row->category_name ?? 'Uncategorized',
                    $row->current_quantity,
                    $row->reorder_level,
                    number_format($row->unit_cost, 2),
                    number_format($row->total_value, 2)
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
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
            'note' => 'This feature will be implemented when product movement tracking is available'
        ]);
    }
}
