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

        return view('admin.inventory.requisitions.my-stock', compact('activeStore', 'myStores', 'stores'));
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

        $query = StockBatchTransaction::with(['stockBatch.product', 'performer', 'reference'])
            ->whereHas('stockBatch', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });

        // Apply date range filters
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->inDateRange(
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            );
        }

        // Apply transaction type filter
        if ($request->filled('transaction_type')) {
            $query->where('type', $request->transaction_type);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('product', function ($t) {
                return $t->stockBatch->product->product_name ?? 'N/A';
            })
            ->editColumn('batch', function ($t) {
                return $t->stockBatch->batch_number ?? 'N/A';
            })
            ->editColumn('type', function ($t) {
                return '<span class="badge ' . $t->type_badge_class . '">' . $t->type_label . '</span>';
            })
            ->editColumn('qty', function ($t) {
                $class = $t->qty < 0 ? 'text-danger' : 'text-success';
                return '<span class="' . $class . ' font-weight-bold">' . $t->qty . '</span>';
            })
            ->editColumn('performer', function ($t) {
                return $t->performer->name ?? 'System';
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
            ->editColumn('created_at', function ($t) {
                return $t->created_at->format('Y-m-d H:i:s');
            })
            ->rawColumns(['type', 'qty', 'reference'])
            ->make(true);
    }
}
