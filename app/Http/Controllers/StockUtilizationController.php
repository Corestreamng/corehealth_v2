<?php

namespace App\Http\Controllers;

use App\Helpers\HmoHelper;
use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductOrServiceRequest;
use App\Models\ProductRequest;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\StockBatch;
use App\Models\StockBatchTransaction;
use App\Models\StockUtilization;
use App\Services\StockService;
use App\Services\StoreContextResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class StockUtilizationController extends Controller
{
    protected StockService $stockService;
    protected StoreContextResolver $resolver;

    public function __construct(StockService $stockService, StoreContextResolver $resolver)
    {
        $this->stockService = $stockService;
        $this->resolver = $resolver;
    }

    /**
     * Show the main My Stock dashboard view
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $myStores = $this->resolver->candidateStores($user);

        $storeId = $request->get('store_id');
        $activeStore = null;

        if ($storeId) {
            $hasAccess = $user->hasPermissionTo('stores.candidate-all') || $myStores->contains('id', $storeId);
            if ($hasAccess) {
                $activeStore = Store::find($storeId);
            }
        }

        if (!$activeStore) {
            $activeStore = $this->resolver->resolve($user) ?? $myStores->first();
        }

        // Fetch all stores for the dropdown/switcher
        $stores = Store::active()->orderBy('store_name')->get();

        $categories = \App\Models\ProductCategory::orderBy('category_name')->get();

        return view('admin.inventory.requisitions.my-stock', compact('activeStore', 'myStores', 'stores', 'categories'));
    }

    /**
     * Retrieve products in store for card-based grid (AJAX pagination/search)
     */
    public function getProducts(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'search' => 'nullable|string',
            'stock_level' => 'nullable|string|in:all,low,out,expiring_soon,expired'
        ]);

        $storeId = $request->store_id;
        $user = auth()->user();

        // Security check
        $myStores = $this->resolver->candidateStores($user);
        if (!$user->hasPermissionTo('stores.candidate-all') && !$myStores->contains('id', $storeId)) {
            return response()->json(['error' => 'Unauthorized store access.'], 403);
        }

        $query = StoreStock::with(['product.price', 'product.category'])
            ->where('store_id', $storeId)
            ->active();

        // Apply stock level filters
        if ($request->stock_level === 'low') {
            $query->whereRaw('current_quantity <= IFNULL(NULLIF(reorder_level, 0), (SELECT reorder_alert FROM products WHERE products.id = store_stocks.product_id))');
        } elseif ($request->stock_level === 'out') {
            $query->outOfStock();
        } elseif ($request->stock_level === 'expiring_soon') {
            $query->whereExists(function ($q) use ($storeId) {
                $q->select(DB::raw(1))
                  ->from('stock_batches')
                  ->whereColumn('stock_batches.product_id', 'store_stocks.product_id')
                  ->where('stock_batches.store_id', $storeId)
                  ->where('stock_batches.current_qty', '>', 0)
                  ->where('stock_batches.expiry_date', '<=', Carbon::now()->addMonths(3))
                  ->where('stock_batches.expiry_date', '>=', Carbon::now());
            });
        } elseif ($request->stock_level === 'expired') {
            $query->whereExists(function ($q) use ($storeId) {
                $q->select(DB::raw(1))
                  ->from('stock_batches')
                  ->whereColumn('stock_batches.product_id', 'store_stocks.product_id')
                  ->where('stock_batches.store_id', $storeId)
                  ->where('stock_batches.current_qty', '>', 0)
                  ->where('stock_batches.expiry_date', '<', Carbon::now());
            });
        }

        // Apply search query
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                  ->orWhere('product_code', 'like', "%{$search}%");
            });
        }

        $products = $query->paginate(12);

        // Manually attach batches — the hasMany on StoreStock uses product_id as FK which
        // breaks standard eager-loading across multiple store_ids. We load them directly.
        $productIds = $products->pluck('product_id')->unique()->values()->all();

        $batchMap = StockBatch::where('store_id', $storeId)
            ->whereIn('product_id', $productIds)
            ->where('current_qty', '>', 0)
            ->active()
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date', 'asc')
            ->get(['id', 'product_id', 'batch_number', 'batch_name', 'current_qty', 'expiry_date', 'source'])
            ->groupBy('product_id');

        // Inject batches into each paginated item
        $products->getCollection()->transform(function ($item) use ($batchMap) {
            $item->batches = $batchMap->get($item->product_id, collect())->values();
            return $item;
        });

        return response()->json($products);
    }

    /**
     * Retrieve active stock batches for a given product in store
     */
    public function getBatches(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_id' => 'required|exists:products,id'
        ]);

        $storeId = $request->store_id;
        $productId = $request->product_id;

        $batches = StockBatch::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->active()
            ->hasStock()
            ->fefoOrder() // Order by FEFO so users see closest expiration first
            ->get(['id', 'batch_number', 'batch_name', 'current_qty', 'expiry_date', 'received_date']);

        return response()->json($batches);
    }

    /**
     * Search patient list for utilization assignment
     */
    public function searchPatients(Request $request)
    {
        $term = $request->get('term', '');

        $patients = Patient::with(['user', 'hmo'])
            ->where(function ($q) use ($term) {
                $q->whereHas('user', function ($sub) use ($term) {
                    $sub->where('surname', 'like', "%{$term}%")
                        ->orWhere('firstname', 'like', "%{$term}%")
                        ->orWhere('othername', 'like', "%{$term}%");
                })
                ->orWhere('file_no', 'like', "%{$term}%")
                ->orWhere('phone_no', 'like', "%{$term}%");
            })
            ->limit(20)
            ->get()
            ->map(function ($patient) {
                return [
                    'id' => $patient->id,
                    'user_id' => $patient->user_id,
                    'name' => userfullname($patient->user_id),
                    'file_no' => $patient->file_no,
                    'hmo_name' => optional($patient->hmo)->name ?? 'None / Cash',
                    'hmo_id' => $patient->hmo_id
                ];
            });

        return response()->json($patients);
    }

    /**
     * Search staff for performer filter
     */
    public function searchPerformers(Request $request)
    {
        $term = $request->get('term', '');
        
        $users = \App\Models\User::where('status', 1)
            ->where('is_admin', '!=', 19)
            ->where(function ($q) use ($term) {
                $q->where('firstname', 'like', "%{$term}%")
                  ->orWhere('surname', 'like', "%{$term}%")
                  ->orWhere('othername', 'like', "%{$term}%");
            })
            ->limit(20)
            ->get(['id', 'firstname', 'surname', 'othername'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => trim($user->firstname . ' ' . $user->surname . ' ' . $user->othername)
                ];
            });

        return response()->json($users);
    }

    /**
     * Get real-time HMO Tariff pricing preview
     */
    public function getTariffPreview(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1'
        ]);

        $patient = Patient::findOrFail($request->patient_id);
        $productId = $request->product_id;
        $qty = $request->qty;

        try {
            if ($patient->hmo_id) {
                $hmoData = HmoHelper::applyHmoTariff($patient->id, $productId, null);
                if ($hmoData) {
                    return response()->json([
                        'success' => true,
                        'hmo_name' => optional($patient->hmo)->name,
                        'payable_amount' => $hmoData['payable_amount'] * $qty,
                        'claims_amount' => $hmoData['claims_amount'] * $qty,
                        'coverage_mode' => $hmoData['coverage_mode']
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('HMO tariff preview lookup failed: ' . $e->getMessage());
        }

        // Fallback to cash price
        $product = Product::with('price')->findOrFail($productId);
        $price = optional($product->price)->current_sale_price ?? 0;

        return response()->json([
            'success' => true,
            'hmo_name' => 'Cash / None',
            'payable_amount' => $price * $qty,
            'claims_amount' => 0,
            'coverage_mode' => 'none'
        ]);
    }

    /**
     * Submit stock utilization log and handle billing if applicable
     */
    public function utilize(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'unit' => 'nullable|string',
            'reason' => 'required|string',
            'utilization_type' => 'required|in:internal,patient',
            'patient_id' => 'required_if:utilization_type,patient|nullable|exists:patients,id',
            'is_billed' => 'boolean',
            'strategy' => 'required|in:fifo,fefo,batch',
            'stock_batch_id' => 'required_if:strategy,batch|nullable|exists:stock_batches,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);

        $user = auth()->user();
        $storeId = $request->store_id;
        $productId = $request->product_id;
        $qty = $request->qty;

        // Security check
        $myStores = $this->resolver->candidateStores($user);
        if (!$user->hasPermissionTo('stores.candidate-all') && !$myStores->contains('id', $storeId)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized store access.'], 403);
        }

        try {
            $result = DB::transaction(function () use ($request, $user, $storeId, $productId, $qty) {
                $productOrServiceRequestId = null;
                $patient = null;

                // 1. Resolve Patient billing if required
                if ($request->utilization_type === 'patient') {
                    $patient = Patient::findOrFail($request->patient_id);

                    if ($request->is_billed) {
                        // Create POSR
                        $billReq = new ProductOrServiceRequest();
                        $billReq->user_id = $patient->user_id;
                        $billReq->staff_user_id = $user->id;
                        $billReq->product_id = $productId;
                        $billReq->dispensed_from_store_id = $storeId;
                        $billReq->qty = $qty;
                        $billReq->hmo_id = $patient->hmo_id ?? null;

                        // Apply HMO Tariff
                        $hmoData = null;
                        try {
                            if ($patient->hmo_id) {
                                $hmoData = HmoHelper::applyHmoTariff($patient->id, $productId, null);
                            }
                        } catch (\Exception $e) {
                            Log::warning('HMO Tariff calculation failed during utilization: ' . $e->getMessage());
                        }

                        if ($hmoData) {
                            $billReq->payable_amount = $hmoData['payable_amount'] * $qty;
                            $billReq->claims_amount = $hmoData['claims_amount'] * $qty;
                            $billReq->coverage_mode = $hmoData['coverage_mode'];
                            $billReq->validation_status = $hmoData['validation_status'] ?? 'pending';
                        } else {
                            $product = Product::with('price')->find($productId);
                            $price = optional($product->price)->current_sale_price ?? 0;
                            $billReq->payable_amount = $price * $qty;
                            $billReq->claims_amount = 0;
                            $billReq->coverage_mode = 'none';
                            $billReq->validation_status = 'pending';
                        }

                        $billReq->save();
                        $productOrServiceRequestId = $billReq->id;

                        // Create ProductRequest (status 3: Dispensed)
                        $presc = new ProductRequest();
                        $presc->product_id = $productId;
                        $presc->dose = $request->notes ?? $request->reason;
                        $presc->billed_by = $user->id;
                        $presc->billed_date = now();
                        $presc->patient_id = $patient->id;
                        $presc->doctor_id = $user->id;
                        $presc->product_request_id = $billReq->id;
                        $presc->status = 3;
                        $presc->dispensed_by = $user->id;
                        $presc->dispense_date = now();
                        $presc->dispensed_from_store_id = $storeId;
                        $presc->save();
                    }
                }

                // 2. Create StockUtilization record
                $util = StockUtilization::create([
                    'product_id' => $productId,
                    'store_id' => $storeId,
                    'qty' => $qty,
                    'unit' => $request->unit,
                    'reason' => $request->reason,
                    'utilization_type' => $request->utilization_type,
                    'patient_id' => $request->patient_id,
                    'is_billed' => $request->is_billed ?? false,
                    'product_or_service_request_id' => $productOrServiceRequestId,
                    'start_date' => $request->start_date ? Carbon::parse($request->start_date) : null,
                    'end_date' => $request->end_date ? Carbon::parse($request->end_date) : null,
                    'notes' => $request->notes,
                    'created_by' => $user->id
                ]);

                // 3. Deduct stock using the StockService utilizing method
                $noteMsg = $request->utilization_type === 'patient'
                    ? "Utilized for patient: " . ($patient ? ($patient->user->name . ' (' . $patient->file_no . ')') : '')
                    : "Department internal utilization: " . $request->reason;

                $this->stockService->utilizeStock(
                    $productId,
                    $storeId,
                    $qty,
                    $request->strategy,
                    $request->stock_batch_id,
                    StockUtilization::class,
                    $util->id,
                    $noteMsg
                );

                return $util;
            });

            return response()->json([
                'success' => true,
                'message' => 'Stock utilization logged successfully.',
                'utilization' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Stock utilization recording failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to record stock utilization: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Datatables history of all stock movements for the active store
     */
    public function history(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date'
        ]);

        $storeId = $request->store_id;
        $user = auth()->user();

        // Security check
        $myStores = $this->resolver->candidateStores($user);
        if (!$user->hasPermissionTo('stores.candidate-all') && !$myStores->contains('id', $storeId)) {
            return response()->json(['error' => 'Unauthorized store access.'], 403);
        }

        $subquery = \App\Models\StockBatchTransaction::from('stock_batch_transactions as t2')
            ->selectRaw('SUM(CASE WHEN t2.type IN ("in", "transfer_in", "return", "req_return") THEN t2.qty WHEN t2.type IN ("out", "transfer_out", "expired", "damaged", "po_return") THEN -t2.qty WHEN t2.type = "adjustment" AND t2.notes LIKE "Positive%" THEN t2.qty WHEN t2.type = "adjustment" AND t2.notes NOT LIKE "Positive%" THEN -t2.qty ELSE 0 END)')
            ->join('stock_batches as sb2', 'sb2.id', '=', 't2.stock_batch_id')
            ->whereColumn('sb2.product_id', 'stock_batches.product_id')
            ->whereColumn('sb2.store_id', 'stock_batches.store_id')
            ->where(function($q) {
                $q->whereColumn('t2.created_at', '<', 'stock_batch_transactions.created_at')
                  ->orWhere(function($q2) {
                      $q2->whereColumn('t2.created_at', '=', 'stock_batch_transactions.created_at')
                         ->whereColumn('t2.id', '<=', 'stock_batch_transactions.id');
                  });
            });

        $query = StockBatchTransaction::select('stock_batch_transactions.*')
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_batch_transactions.stock_batch_id')
            ->with(['stockBatch.product.category', 'stockBatch.product.packagings', 'performer', 'reference'])
            ->where('stock_batches.store_id', $storeId)
            ->addSelect([
                'product_running_balance' => $subquery
            ]);

        // Apply date range filters
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('stock_batch_transactions.created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        }

        // Apply transaction type filter
        if ($request->filled('transaction_type')) {
            $query->where('type', $request->transaction_type);
        }

        // Apply product filter
        if ($request->filled('product_id')) {
            $query->whereHas('stockBatch', function ($q) use ($request) {
                $q->where('product_id', $request->product_id);
            });
        }

        // Apply category filter
        if ($request->filled('category_id')) {
            $query->whereHas('stockBatch.product', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        // Apply performer filter
        if ($request->filled('performer_id')) {
            $query->where('performed_by', $request->performer_id);
        }

        // --- Calculate Summary Stats ---
        $statsQuery = clone $query;
        $statsQuery->setEagerLoads([]);
        $statsQuery->getQuery()->columns = null;
        $statsQuery->getQuery()->bindings['select'] = [];
        $typeStats = $statsQuery->selectRaw('type, sum(qty) as total_qty, count(distinct stock_batches.product_id) as unique_products')
                                ->groupBy('type')
                                ->get();

        $totalIn = 0;
        $totalOut = 0;
        $totalDamaged = 0;
        $uniqueProducts = 0;

        foreach ($typeStats as $stat) {
            $qty = $stat->total_qty;
            if (in_array($stat->type, ['in', 'transfer_in', 'return', 'req_return'])) {
                $totalIn += $qty;
            } elseif (in_array($stat->type, ['out', 'transfer_out', 'po_return'])) {
                $totalOut += $qty;
            } elseif (in_array($stat->type, ['expired', 'damaged'])) {
                $totalDamaged += $qty;
                $totalOut += $qty; // Include damages in total out for consistency
            }
            $uniqueProducts = max($uniqueProducts, $stat->unique_products);
        }
        
        // Accurate distinct products count across all types
        $upQuery = clone $query;
        $upQuery->setEagerLoads([]);
        $upQuery->getQuery()->columns = null;
        $upQuery->getQuery()->bindings['select'] = [];
        $uniqueProducts = $upQuery->distinct('stock_batches.product_id')->count('stock_batches.product_id');

        $summary = [
            'mode' => $request->filled('product_id') ? 'product' : 'generic',
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'total_damaged' => $totalDamaged,
            'unique_products' => $uniqueProducts,
            'base_unit' => '',
            'total_in_formatted' => $totalIn,
            'total_out_formatted' => $totalOut,
            'total_in_bulk' => '',
            'total_out_bulk' => '',
            'opening_balance_formatted' => '',
            'opening_balance_bulk' => '',
            'closing_balance_formatted' => '',
            'closing_balance_bulk' => ''
        ];

        if ($request->filled('product_id')) {
            $product = \App\Models\Product::with('packagings')->find($request->product_id);
            if ($product) {
                $summary['base_unit'] = $product->baseQtyLabel();
                $summary['total_in_formatted'] = $product->formatQty($totalIn);
                $summary['total_out_formatted'] = $product->formatQty($totalOut);
                $summary['total_in_bulk'] = $product->formatBulkQty($totalIn);
                $summary['total_out_bulk'] = $product->formatBulkQty($totalOut);
            }

            $baseBalQuery = \App\Models\StockBatchTransaction::whereHas('stockBatch', function($q) use($storeId, $request) {
                $q->where('store_id', $storeId)->where('product_id', $request->product_id);
            });
            $rawSum = 'SUM(CASE WHEN type IN ("in", "transfer_in", "return", "req_return") THEN qty WHEN type IN ("out", "transfer_out", "expired", "damaged", "po_return") THEN -qty WHEN type = "adjustment" AND notes LIKE "Positive%" THEN qty WHEN type = "adjustment" AND notes NOT LIKE "Positive%" THEN -qty ELSE 0 END) as aggregate';
            
            $closing = (clone $baseBalQuery)->selectRaw($rawSum)->value('aggregate') ?? 0;
            $summary['closing_balance'] = $closing;
            
            if ($request->filled('start_date')) {
                $opening = (clone $baseBalQuery)
                    ->where('created_at', '<', Carbon::parse($request->start_date)->startOfDay())
                    ->selectRaw($rawSum)->value('aggregate') ?? 0;
                $summary['opening_balance'] = $opening;
            } else {
                $summary['opening_balance'] = 0;
            }
            
            if ($product) {
                $summary['closing_balance_formatted'] = $product->formatQty($summary['closing_balance']);
                $summary['closing_balance_bulk'] = $product->formatBulkQty($summary['closing_balance']);
                $summary['opening_balance_formatted'] = $product->formatQty($summary['opening_balance']);
                $summary['opening_balance_bulk'] = $product->formatBulkQty($summary['opening_balance']);
            }
        }

        return DataTables::of($query)
            ->with('summary_stats', $summary)
            ->filterColumn('performer.name', function($query, $keyword) {
                $query->whereHas('performer', function($q) use ($keyword) {
                    $q->where('firstname', 'like', "%{$keyword}%")
                      ->orWhere('surname', 'like', "%{$keyword}%")
                      ->orWhere('othername', 'like', "%{$keyword}%");
                });
            })
            ->addIndexColumn()
            ->editColumn('created_at', function ($t) {
                $date = $t->created_at->format('M d, Y');
                $time = $t->created_at->format('h:i A');
                $human = $t->created_at->diffForHumans();
                return '<div class="font-weight-bold">' . $date . '</div>' .
                       '<small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $time . ' (' . $human . ')</small>';
            })
            ->editColumn('product', function ($t) {
                $productName = $t->stockBatch->product->product_name ?? 'N/A';
                $productCode = $t->stockBatch->product->product_code ?? 'No Code';
                $categoryName = $t->stockBatch->product->category->category_name ?? 'No Category';
                return '<div class="font-weight-bold text-dark">' . $productName . '</div>' .
                       '<div class="small mt-1"><span class="text-muted border-right pr-1 mr-1">Code: ' . $productCode . '</span>' .
                       '<span class="text-info"><i class="mdi mdi-tag"></i> ' . $categoryName . '</span></div>';
            })
            ->editColumn('batch', function ($t) {
                $batchNumber = $t->stockBatch->batch_number ?? 'N/A';
                $expiryDate = $t->stockBatch->expiry_date;
                $expiryHtml = '';
                if ($expiryDate) {
                    $exp = \Carbon\Carbon::parse($expiryDate);
                    $expClass = $exp->isPast() ? 'text-danger font-weight-bold' : ($exp->diffInDays(now()) < 90 ? 'text-warning' : 'text-muted');
                    $expiryHtml = '<div class="small mt-1"><span class="text-muted">Exp: </span><span class="' . $expClass . '">' . $exp->format('Y-m-d') . '</span></div>';
                }
                return '<div class="font-weight-bold">' . $batchNumber . '</div>' . $expiryHtml;
            })
            ->editColumn('type', function ($t) {
                return '<span class="badge ' . $t->type_badge_class . '">' . $t->type_label . '</span>';
            })
            ->editColumn('qty', function ($t) {
                $isOut = in_array($t->type, ['out', 'transfer_out', 'expired', 'damaged', 'po_return']);
                $isNegAdj = $t->type === 'adjustment' && !str_starts_with($t->notes ?? '', 'Positive');
                $sign = ($isOut || $isNegAdj) ? '-' : '+';
                $badgeClass = ($isOut || $isNegAdj) ? 'badge-danger' : 'badge-success';
                $signedQty = ($isOut || $isNegAdj) ? -abs($t->qty) : abs($t->qty);
                $product = $t->stockBatch->product;
                
                $qtyFormatted = $product ? $product->formatQty(abs($t->qty)) : abs($t->qty);
                $qtyBulk = $product ? $product->formatBulkQty(abs($t->qty)) : '';
                
                $html = '<span class="badge ' . $badgeClass . '" style="font-size: 1.1em; padding: 0.4em 0.6em;">' . $sign . $qtyFormatted . '</span>';
                if ($qtyBulk) {
                    $html .= '<div class="small text-muted mt-1">' . $qtyBulk . '</div>';
                }
                
                if (isset($t->product_running_balance) || isset($t->balance_after)) {
                    $html .= '<hr class="my-2" style="border-color: #eee;">';
                    $html .= '<div class="small text-nowrap" style="line-height: 1.4;">';
                    
                    if (isset($t->product_running_balance)) {
                        $totalAfter = $t->product_running_balance;
                        $totalBefore = $totalAfter - $signedQty;
                        
                        $tbf = $product ? $product->formatQty($totalBefore) : $totalBefore;
                        $taf = $product ? $product->formatQty($totalAfter) : $totalAfter;
                        $tbBulk = $product ? $product->formatBulkQty($totalBefore) : '';
                        $taBulk = $product ? $product->formatBulkQty($totalAfter) : '';

                        $html .= '<div class="text-dark mb-2" title="Total inventory across all batches">
                                    <i class="mdi mdi-layers text-primary mr-1"></i> <strong>Overall Stock</strong>
                                    <div class="text-muted" style="margin-left: 1.25rem;">
                                        ' . $tbf . ' <i class="mdi mdi-arrow-right mx-1" style="font-size: 10px;"></i> <strong class="text-dark">' . $taf . '</strong>
                                    </div>';
                        if ($taBulk) {
                            $html .= '<div class="text-muted" style="margin-left: 1.25rem; font-size: 0.85em;">≈ ' . $taBulk . '</div>';
                        }
                        $html .= '</div>';
                    }
                    
                    if (isset($t->balance_after)) {
                        $batchAfter = $t->balance_after;
                        $batchBefore = $batchAfter - $signedQty;
                        
                        $bbf = $product ? $product->formatQty($batchBefore) : $batchBefore;
                        $baf = $product ? $product->formatQty($batchAfter) : $batchAfter;

                        $html .= '<div class="text-dark" title="Inventory in this specific batch">
                                    <i class="mdi mdi-package-variant-closed text-muted mr-1"></i> <strong>Batch Stock</strong>
                                    <div class="text-muted" style="margin-left: 1.25rem;">
                                        ' . $bbf . ' <i class="mdi mdi-arrow-right mx-1" style="font-size: 10px;"></i> <strong class="text-dark">' . $baf . '</strong>
                                    </div>
                                  </div>';
                    }
                    
                    $html .= '</div>';
                }
                
                return $html;
            })
            ->editColumn('performer', function ($t) {
                $name = $t->performer->name ?? 'System';
                $idLabel = $t->performer ? 'ID: #' . $t->performer->id : 'Automated';
                return '<div class="font-weight-bold">' . $name . '</div><small class="text-muted">' . $idLabel . '</small>';
            })
            ->editColumn('reference', function ($t) {
                // If it is our custom StockUtilization
                if ($t->reference_type === StockUtilization::class && $t->reference) {
                    $u = $t->reference;
                    if ($u->utilization_type === 'patient') {
                        $pName = $u->patient ? userfullname($u->patient->user_id) : 'Patient';
                        $fileNo = $u->patient && $u->patient->file_no ? " [{$u->patient->file_no}]" : '';
                        $billingStatus = $u->is_billed ? ' (Billed)' : ' (Unbilled)';
                        return "Stock Utilization (Patient): {$pName}{$fileNo}{$billingStatus} - {$u->reason}";
                    }
                    return "Stock Utilization (Internal): {$u->reason}";
                }

                // If it is MedicationAdministration
                if (str_contains((string)$t->reference_type, 'MedicationAdministration') && $t->reference) {
                    $pName = optional($t->reference->patient)->user_id ? userfullname($t->reference->patient->user_id) : 'Patient';
                    $fileNo = optional($t->reference->patient)->file_no ? " [" . $t->reference->patient->file_no . "]" : '';
                    return "Medication Administered to {$pName}{$fileNo} - Dose: " . ($t->reference->dose ?? 'N/A');
                }

                // If it is InjectionAdministration
                if (str_contains((string)$t->reference_type, 'InjectionAdministration') && $t->reference) {
                    $pName = optional($t->reference->patient)->user_id ? userfullname($t->reference->patient->user_id) : 'Patient';
                    $fileNo = optional($t->reference->patient)->file_no ? " [" . $t->reference->patient->file_no . "]" : '';
                    return "Injection Administered to {$pName}{$fileNo} - Dose: " . ($t->reference->dose ?? 'N/A');
                }

                // If it is VaccineAdministration
                if (str_contains((string)$t->reference_type, 'VaccineAdministration') && $t->reference) {
                    $pName = optional($t->reference->patient)->user_id ? userfullname($t->reference->patient->user_id) : 'Patient';
                    $fileNo = optional($t->reference->patient)->file_no ? " [" . $t->reference->patient->file_no . "]" : '';
                    return "Vaccine Administered to {$pName}{$fileNo}";
                }

                // If it is ProductRequest (Pharmacy Dispense)
                if (str_contains((string)$t->reference_type, 'ProductRequest') && $t->reference) {
                    $pName = optional($t->reference->patient)->user_id ? userfullname($t->reference->patient->user_id) : 'Patient';
                    $fileNo = optional($t->reference->patient)->file_no ? " [" . $t->reference->patient->file_no . "]" : '';
                    return "Pharmacy Dispense (Prescription) for {$pName}{$fileNo}";
                }

                // If it is ProductOrServiceRequest (Consumable/Direct Billing)
                if (str_contains((string)$t->reference_type, 'ProductOrServiceRequest') && $t->reference) {
                    $pName = optional($t->reference->patient)->user_id ? userfullname($t->reference->patient->user_id) : 'Patient';
                    $fileNo = optional($t->reference->patient)->file_no ? " [" . $t->reference->patient->file_no . "]" : '';
                    return "Consumable Billing/Direct Dispense for {$pName}{$fileNo}";
                }

                // If it is StoreRequisitionReturn
                if (str_contains((string)$t->reference_type, 'StoreRequisitionReturn') && $t->reference) {
                    $retName = $t->reference->return_number ?? 'Return #' . $t->reference->id;
                    if ($t->is_inbound) {
                        return "Stock Returned to Store: " . $retName;
                    } else {
                        return "Requisition Return Sent: " . $retName;
                    }
                }

                // If it is StoreRequisition
                if (str_contains((string)$t->reference_type, 'StoreRequisition') && $t->reference) {
                    $reqName = $t->reference->requisition_number ?? 'Requisition #' . $t->reference->id;
                    if ($t->is_inbound) {
                        return "Requisition Fulfillment Received: " . $reqName;
                    } else {
                        return "Dispatched for Requisition: " . $reqName;
                    }
                }

                // Initial Stock or Manual Adjustments without a specific reference
                if (!$t->reference_type) {
                    if ($t->type === 'in') {
                        return $t->notes ?? "Initial Stock / Manual Entry";
                    } elseif ($t->type === 'adjustment') {
                        return "Manual Stock Adjustment" . ($t->notes ? ": {$t->notes}" : "");
                    }
                }

                // Fallbacks for other transaction types
                return $t->notes ?? $t->type_label;
            })
            ->rawColumns(['created_at', 'product', 'batch', 'type', 'qty', 'performer', 'reference'])
            ->make(true);
    }
}
