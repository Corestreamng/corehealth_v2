<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\ProductRequest;
use App\Models\ProductOrServiceRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Hmo;
use App\Helpers\HmoHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use PDF;

/**
 * PharmacyWorkbenchController
 *
 * References:
 * - Plan Section: Phase 1.1 - Backend Setup
 * - Models: ProductRequest, ProductOrServiceRequest, Product, Patient
 * - Related Controllers: ProductRequestController (dispense/bill methods)
 */
class PharmacyWorkbenchController extends Controller
{
    /**
     * Display the pharmacy workbench main page
     */
    public function index()
    {
        $stores = Store::where('status', 1)->get();
        return view('admin.pharmacy.workbench', compact('stores'));
    }

    /**
     * Get all active stores for dropdown selection
     */
    public function getStores()
    {
        $stores = Store::where('status', 1)
            ->select('id', 'store_name', 'location')
            ->get();

        return response()->json($stores);
    }

    /**
     * Get stock availability for a product across all stores
     */
    public function getProductStockByStore($productId)
    {
        $product = Product::with(['stock', 'storeStock.store'])->find($productId);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $stocks = [
            'global_stock' => $product->stock ? $product->stock->current_quantity : 0,
            'stores' => $product->storeStock->map(function ($ss) {
                return [
                    'store_id' => $ss->store_id,
                    'store_name' => $ss->store ? $ss->store->store_name : 'Unknown',
                    'quantity' => $ss->current_quantity
                ];
            })
        ];

        return response()->json($stocks);
    }

    /**
     * DataTables endpoint for prescriptions pending billing (status=1)
     * Matches EncounterController::prescBillList() format
     */
    public function prescBillList($patientId)
    {
        $items = ProductRequest::with(['product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest', 'doctor', 'biller'])
            ->where('status', 1)
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'DESC')
            ->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('select', function ($item) {
                $price = optional(optional($item->product)->price)->current_sale_price ?? 0;
                return "<input type='checkbox' name='selectedPrescBillRows[]' onclick='checkPrescBillRow(this)' data-price='{$price}' value='{$item->id}' class='form-control'>";
            })
            ->editColumn('dose', function ($item) {
                $code = optional($item->product)->product_code ?? '';
                $name = optional($item->product)->product_name ?? 'Unknown';
                $str = "<span class='badge badge-success'>[{$code}] {$name}</span>";
                $str .= '<hr><b>Dose/Freq:</b> ' . ($item->dose ?? 'N/A');
                $str .= '<br><b>Qty:</b> ' . ($item->qty ?? 1);
                return $str;
            })
            ->editColumn('created_at', function ($item) {
                $str = '<small>';
                $str .= '<b>Requested By:</b> ' . ($item->doctor_id ? userfullname($item->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($item->created_at)) . ')' : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= '<b>Last Updated:</b> ' . date('h:i a D M j, Y', strtotime($item->updated_at)) . '<br>';
                $str .= '</small>';
                return $str;
            })
            ->rawColumns(['select', 'dose', 'created_at'])
            ->make(true);
    }

    /**
     * DataTables endpoint for prescriptions pending dispense (status=2)
     * Matches EncounterController::prescDispenseList() format
     */
    public function prescDispenseList($patientId)
    {
        $items = ProductRequest::with(['product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller'])
            ->where('status', 2)
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'DESC')
            ->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('select', function ($item) {
                $posr = $item->productOrServiceRequest;
                $isPaid = optional(optional($posr)->payment)->payment_status === 'paid';
                $isValidated = optional($posr)->validation_status === 'validated';
                $canDispense = $isPaid || $isValidated;

                $disabled = !$canDispense ? 'disabled' : '';
                $tooltip = !$canDispense ? 'title="Payment or HMO validation required"' : '';

                return "<input type='checkbox' name='selectedPrescDispenseRows[]' {$disabled} {$tooltip} value='{$item->id}' class='form-control'>";
            })
            ->editColumn('dose', function ($item) {
                $code = optional($item->product)->product_code ?? '';
                $name = optional($item->product)->product_name ?? 'Unknown';
                $posr = $item->productOrServiceRequest;

                $str = "<span class='badge badge-success'>[{$code}] {$name}</span>";

                // Show HMO/payment status
                if ($posr) {
                    $isPaid = optional($posr->payment)->payment_status === 'paid';
                    $isValidated = $posr->validation_status === 'validated';
                    $coverageMode = $posr->coverage_mode ?? 'none';

                    if ($coverageMode !== 'none' && $posr->claims_amount > 0) {
                        $validationBadge = $isValidated
                            ? "<span class='badge badge-success'>HMO Validated</span>"
                            : "<span class='badge badge-warning'>Awaiting HMO Validation</span>";
                        $str .= '<br>' . $validationBadge;
                    }

                    $paymentBadge = $isPaid
                        ? "<span class='badge badge-success'>Paid</span>"
                        : "<span class='badge badge-danger'>Unpaid</span>";
                    $str .= ' ' . $paymentBadge;

                    $str .= '<br><small>Pay: ₦' . number_format($posr->payable_amount ?? 0, 2) . '</small>';
                    if ($posr->claims_amount > 0) {
                        $str .= '<br><small>HMO Claim: ₦' . number_format($posr->claims_amount, 2) . '</small>';
                    }
                }

                $str .= '<hr><b>Dose/Freq:</b> ' . ($item->dose ?? 'N/A');
                $str .= '<br><b>Qty:</b> ' . ($item->qty ?? 1);
                return $str;
            })
            ->editColumn('created_at', function ($item) {
                $str = '<small>';
                $str .= '<b>Requested By:</b> ' . ($item->doctor_id ? userfullname($item->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($item->created_at)) . ')' : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= '<b>Billed By:</b> ' . ($item->billed_by ? userfullname($item->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($item->billed_date)) . ')' : "<span class='badge badge-secondary'>Not billed</span>") . '<br>';
                $str .= '</small>';
                return $str;
            })
            ->rawColumns(['select', 'dose', 'created_at'])
            ->make(true);
    }

    /**
     * DataTables endpoint for dispensed prescriptions history (status=3)
     */
    public function prescHistoryList($patientId)
    {
        $items = ProductRequest::with(['product', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller', 'dispenser'])
            ->where('status', 3)
            ->where('patient_id', $patientId)
            ->orderBy('dispense_date', 'DESC')
            ->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->editColumn('dose', function ($item) {
                $code = optional($item->product)->product_code ?? '';
                $name = optional($item->product)->product_name ?? 'Unknown';
                $posr = $item->productOrServiceRequest;

                $str = "<span class='badge badge-success'>[{$code}] {$name}</span>";
                $str .= '<hr><b>Dose/Freq:</b> ' . ($item->dose ?? 'N/A');
                $str .= '<br><b>Qty:</b> ' . ($item->qty ?? 1);

                if ($posr) {
                    $str .= '<br><small>Amount: ₦' . number_format($posr->payable_amount ?? 0, 2) . '</small>';
                }
                return $str;
            })
            ->editColumn('created_at', function ($item) {
                $str = '<small>';
                $str .= '<b>Requested By:</b> ' . ($item->doctor_id ? userfullname($item->doctor_id) . ' (' . date('h:i a D M j, Y', strtotime($item->created_at)) . ')' : "<span class='badge badge-secondary'>N/A</span>") . '<br>';
                $str .= '<b>Billed By:</b> ' . ($item->billed_by ? userfullname($item->billed_by) . ' (' . date('h:i a D M j, Y', strtotime($item->billed_date)) . ')' : "N/A") . '<br>';
                $str .= '<b>Dispensed By:</b> ' . ($item->dispensed_by ? userfullname($item->dispensed_by) . ' (' . date('h:i a D M j, Y', strtotime($item->dispense_date)) . ')' : "N/A") . '<br>';
                $str .= '</small>';
                return $str;
            })
            ->rawColumns(['dose', 'created_at'])
            ->make(true);
    }

    /**
     * Search for patients (autocomplete)
     *
     * References:
     * - Plan Section: Tab 2 - Patient Medications (Context-Aware)
     */
    public function searchPatients(Request $request)
    {
        $term = $request->get('term', '');

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $patients = Patient::with('user', 'hmo')
            ->where(function ($query) use ($term) {
                $query->whereHas('user', function ($userQuery) use ($term) {
                    $userQuery->where('surname', 'like', "%{$term}%")
                        ->orWhere('firstname', 'like', "%{$term}%")
                        ->orWhere('othername', 'like', "%{$term}%");
                })
                ->orWhere('file_no', 'like', "%{$term}%")
                ->orWhere('phone_no', 'like', "%{$term}%");
            })
            ->limit(10)
            ->get();

        $results = $patients->map(function ($patient) {
            // Count pending prescriptions (status 1=Requested, 2=Billed)
            $pendingCount = ProductRequest::where('patient_id', $patient->id)
                ->whereIn('status', [1, 2])
                ->count();

            return [
                'id' => $patient->id,
                'user_id' => $patient->user_id,
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'age' => $patient->dob ? Carbon::parse($patient->dob)->age : 'N/A',
                'gender' => $patient->gender ?? 'N/A',
                'phone' => $patient->phone_no ?? 'N/A',
                'photo' => $patient->user->filename ?? null,
                'hmo' => optional($patient->hmo)->name ?? 'Self',
                'hmo_no' => $patient->hmo_no ?? '',
                'pending_count' => $pendingCount,
            ];
        });

        return response()->json($results);
    }

    /**
     * Get prescription queue with optional filters
     *
     * References:
     * - Plan Section: Tab 1 - Prescription Queue (Primary Workspace)
     * - ProductRequest status: 1=Requested, 2=Billed, 3=Dispensed, 0=Dismissed
     */
    public function getPrescriptionQueue(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $query = ProductRequest::query()
            ->whereIn('status', [1, 2]); // Requested or Billed only

        // Apply filters
        if ($filter === 'unbilled') {
            $query->where('status', 1);
        } elseif ($filter === 'billed') {
            $query->where('status', 2);
        } elseif ($filter === 'hmo') {
            // Filter for prescriptions with HMO coverage
            $query->whereHas('productOrServiceRequest', function($q) {
                $q->where('claims_amount', '>', 0);
            });
        }

        $results = $query
            ->select([
                'patient_id',
                DB::raw('COUNT(*) as prescription_count'),
                DB::raw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as unbilled_count'),
                DB::raw('SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as ready_count'),
                DB::raw('MAX(created_at) as last_created')
            ])
            ->groupBy('patient_id')
            ->orderByDesc('last_created')
            ->get();

        // Preload patient data
        $patients = Patient::with('user', 'hmo')
            ->whereIn('id', $results->pluck('patient_id'))
            ->get()
            ->keyBy('id');

        $queue = $results->map(function ($item) use ($patients) {
            $patient = $patients->get($item->patient_id);

            if (!$patient) {
                return null;
            }

            return [
                'patient_id' => $patient->id,
                'patient_name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'prescription_count' => $item->prescription_count,
                'unbilled_count' => $item->unbilled_count,
                'ready_count' => $item->ready_count,
                'hmo' => optional($patient->hmo)->name,
            ];
        })->filter();

        return response()->json($queue->values());
    }

    /**
     * Get queue counts for filters
     *
     * References:
     * - Plan Section: Tab 1 - Prescription Queue filters
     */
    public function getQueueCounts()
    {
        $totalCount = ProductRequest::whereIn('status', [1, 2])
            ->select('patient_id')
            ->distinct()
            ->count();

        $unbilledCount = ProductRequest::where('status', 1)
            ->select('patient_id')
            ->distinct()
            ->count();

        $readyCount = ProductRequest::where('status', 2)
            ->select('patient_id')
            ->distinct()
            ->count();

        $hmoCount = ProductRequest::whereIn('status', [1, 2])
            ->whereHas('productOrServiceRequest', function($q) {
                $q->where('claims_amount', '>', 0);
            })
            ->select('patient_id')
            ->distinct()
            ->count();

        return response()->json([
            'total' => $totalCount,
            'unbilled' => $unbilledCount,
            'ready' => $readyCount,
            'hmo' => $hmoCount,
        ]);
    }

    /**
     * Get patient's prescription data (pending prescriptions)
     *
     * References:
     * - Plan Section: Tab 2 - Patient Medications > Pending Prescriptions
     * - Models: ProductRequest with relationships (product, doctor, biller, productOrServiceRequest)
     */
    public function getPatientPrescriptionData($patientId, Request $request)
    {
        $patient = Patient::with('hmo', 'user')->findOrFail($patientId);
        $statusFilter = $request->input('status');

        // Build query for items
        $query = ProductRequest::with([
                'product.price',
                'product.category',
                'doctor',
                'biller',
                'productOrServiceRequest.payment'
            ])
            ->where('patient_id', $patientId)
            ->whereIn('status', [1, 2]); // Requested or Billed

        // Apply status filter if provided
        if ($statusFilter) {
            if ($statusFilter === 'unbilled') {
                $query->where('status', 1);
            } elseif ($statusFilter === 'billed') {
                // Billed but not yet paid/validated for HMO
                $query->where('status', 2)
                      ->where(function($q) {
                          $q->whereDoesntHave('productOrServiceRequest.payment')
                            ->orWhereHas('productOrServiceRequest', function($sq) {
                                $sq->whereNull('validation_status')
                                   ->orWhere('validation_status', '!=', 'validated');
                            });
                      });
            } elseif ($statusFilter === 'ready') {
                // Ready to dispense: billed AND (paid OR HMO validated)
                $query->where('status', 2)
                      ->where(function($q) {
                          $q->whereHas('productOrServiceRequest', function($sq) {
                                $sq->whereNotNull('payment_id');
                            })
                            ->orWhereHas('productOrServiceRequest', function($sq) {
                                $sq->where('validation_status', 'validated');
                            });
                      });
            }
        }

        $items = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($pr) {
                $basePrice = optional(optional($pr->product)->price)->current_sale_price ?? 0;
                $posr = $pr->productOrServiceRequest;
                $payment = optional($posr)->payment;

                // Determine ready status
                $isPaid = optional($payment)->payment_status === 'paid';
                $isValidated = optional($posr)->validation_status === 'validated';
                $isReady = $isPaid || $isValidated;

                // Status label logic
                $statusLabel = $pr->status == 1 ? 'Unbilled' : 'Billed';
                if ($pr->status == 2 && $isReady) {
                    $statusLabel = 'Ready to Dispense';
                }

                // Get stock information
                $globalStock = 0;
                $storeStocks = [];
                if ($pr->product) {
                    // Global stock
                    $stockRecord = $pr->product->stock;
                    if ($stockRecord) {
                        $globalStock = $stockRecord->current_quantity ?? 0;
                    }

                    // Per-store stock
                    $storeStockRecords = StoreStock::with('store')
                        ->where('product_id', $pr->product->id)
                        ->where('current_quantity', '>', 0)
                        ->get();
                    foreach ($storeStockRecords as $ss) {
                        $storeStocks[] = [
                            'store_id' => $ss->store_id,
                            'store_name' => optional($ss->store)->store_name ?? 'Unknown',
                            'quantity' => $ss->current_quantity ?? 0
                        ];
                    }
                }

                return [
                    'id' => $pr->id,
                    'product_request_id' => $pr->id,
                    'product_name' => optional($pr->product)->product_name,
                    'product_code' => optional($pr->product)->product_code,
                    'category' => optional(optional($pr->product)->category)->category_name,
                    'dose' => $pr->dose ?? 'N/A',
                    'qty' => $pr->qty ?? 1,
                    'status' => $pr->status,
                    'status_label' => $statusLabel,
                    'is_ready' => $isReady,
                    'is_paid' => $isPaid,
                    'is_validated' => $isValidated,
                    'price' => $posr ? $posr->payable_amount : $basePrice,
                    'base_price' => $basePrice,
                    'payable_amount' => optional($posr)->payable_amount ?? $basePrice,
                    'claims_amount' => optional($posr)->claims_amount ?? 0,
                    'coverage_mode' => optional($posr)->coverage_mode ?? 'none',
                    'validation_status' => optional($posr)->validation_status ?? null,
                    'doctor_name' => $pr->doctor ? userfullname($pr->doctor_id) : 'N/A',
                    'billed_by' => $pr->billed_by ? userfullname($pr->billed_by) : null,
                    'billed_date' => $pr->billed_date ? Carbon::parse($pr->billed_date)->format('Y-m-d H:i') : null,
                    'created_at' => $pr->created_at->format('Y-m-d H:i'),
                    'global_stock' => $globalStock,
                    'store_stocks' => $storeStocks,
                ];
            });

        // Get counts for subtabs
        $allPending = ProductRequest::where('patient_id', $patientId)->whereIn('status', [1, 2])->count();
        $unbilledCount = ProductRequest::where('patient_id', $patientId)->where('status', 1)->count();

        // Billed but not ready
        $billedNotReadyCount = ProductRequest::where('patient_id', $patientId)
            ->where('status', 2)
            ->where(function($q) {
                $q->whereDoesntHave('productOrServiceRequest.payment')
                  ->orWhereHas('productOrServiceRequest', function($sq) {
                      $sq->whereNull('validation_status')
                         ->orWhere('validation_status', '!=', 'validated');
                  });
            })
            ->count();

        // Ready to dispense count
        $readyCount = ProductRequest::where('patient_id', $patientId)
            ->where('status', 2)
            ->where(function($q) {
                $q->whereHas('productOrServiceRequest', function($sq) {
                      $sq->whereNotNull('payment_id');
                  })
                  ->orWhereHas('productOrServiceRequest', function($sq) {
                      $sq->where('validation_status', 'validated');
                  });
            })
            ->count();

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'user_id' => $patient->user_id,
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'age' => $patient->dob ? Carbon::parse($patient->dob)->age : 'N/A',
                'gender' => $patient->gender ?? 'N/A',
                'hmo_name' => optional($patient->hmo)->name,
                'hmo_no' => $patient->hmo_no,
                'photo' => $patient->user->filename ?? null,
            ],
            'items' => $items,
            'counts' => [
                'all' => $allPending,
                'unbilled' => $unbilledCount,
                'billed' => $billedNotReadyCount,
                'ready' => $readyCount,
            ],
        ]);
    }

    /**
     * Get patient's dispensing history
     *
     * References:
     * - Plan Section: Tab 3 - Dispensing History
     */
    public function getPatientDispensingHistory($patientId, Request $request)
    {
        $patient = Patient::findOrFail($patientId);

        $query = ProductRequest::with(['product', 'doctor', 'dispenser', 'productOrServiceRequest.payment'])
            ->where('patient_id', $patientId)
            ->where('status', 3); // Dispensed only

        // Apply date filters
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('dispense_date', '>=', $request->from_date);
        }
        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('dispense_date', '<=', $request->to_date);
        }

        $history = $query->orderBy('dispense_date', 'desc')->get();

        $items = $history->map(function ($pr) {
            $posr = $pr->productOrServiceRequest;
            $basePrice = optional(optional($pr->product)->price)->current_sale_price ?? 0;
            return [
                'product_request_id' => $pr->id,
                'product_name' => optional($pr->product)->product_name,
                'product_code' => optional($pr->product)->product_code,
                'dose' => $pr->dose ?? 'N/A',
                'qty' => $pr->qty ?? 1,
                'dispensed_by' => $pr->dispensed_by ? userfullname($pr->dispensed_by) : 'N/A',
                'dispense_date' => $pr->dispense_date ? Carbon::parse($pr->dispense_date)->format('Y-m-d H:i') : null,
                'doctor_name' => $pr->doctor ? userfullname($pr->doctor_id) : 'N/A',
                'payment_type' => optional(optional($posr)->payment)->payment_type ?? 'N/A',
                'base_price' => $basePrice,
                'payable_amount' => $posr->payable_amount ?? $basePrice,
                'claims_amount' => $posr->claims_amount ?? 0,
            ];
        });

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
            ],
            'items' => $items,
        ]);
    }

    /**
     * Validate stock availability for cart items before dispense
     * Returns detailed stock info for each item
     */
    public function validateCartStock(Request $request)
    {
        $request->validate([
            'product_request_ids' => 'required|array',
            'product_request_ids.*' => 'exists:product_requests,id',
            'store_id' => 'required|exists:stores,id'
        ]);

        $storeId = $request->store_id;
        $store = Store::find($storeId);
        $validationResults = [];
        $allValid = true;
        $totalIssues = 0;

        foreach ($request->product_request_ids as $prId) {
            $productRequest = ProductRequest::with(['productOrServiceRequest', 'product.stock'])->find($prId);

            if (!$productRequest) {
                $validationResults[] = [
                    'product_request_id' => $prId,
                    'valid' => false,
                    'error' => 'Product request not found',
                    'error_type' => 'not_found'
                ];
                $allValid = false;
                $totalIssues++;
                continue;
            }

            $result = [
                'product_request_id' => $prId,
                'product_id' => $productRequest->product_id,
                'product_name' => $productRequest->product->name ?? 'Unknown',
                'qty_required' => $productRequest->qty ?? 1,
                'valid' => true,
                'error' => null,
                'error_type' => null
            ];

            // Check status
            if ($productRequest->status != 2) {
                $result['valid'] = false;
                $result['error'] = 'Item not billed (status: ' . $productRequest->status . ')';
                $result['error_type'] = 'not_billed';
                $allValid = false;
                $totalIssues++;
                $validationResults[] = $result;
                continue;
            }

            // Check HMO delivery requirements
            if ($productRequest->productOrServiceRequest) {
                $deliveryCheck = HmoHelper::canDeliverService($productRequest->productOrServiceRequest);
                if (!$deliveryCheck['can_deliver']) {
                    $result['valid'] = false;
                    $result['error'] = $deliveryCheck['reason'];
                    $result['error_type'] = 'hmo_block';
                    $allValid = false;
                    $totalIssues++;
                    $validationResults[] = $result;
                    continue;
                }
            }

            // Check store stock
            $qty = $productRequest->qty ?? 1;
            $storeStock = StoreStock::where('store_id', $storeId)
                ->where('product_id', $productRequest->product_id)
                ->first();

            $storeQty = $storeStock ? $storeStock->current_quantity : 0;
            $result['store_qty_available'] = $storeQty;

            if ($storeQty < $qty) {
                $result['valid'] = false;
                $result['error'] = "Insufficient stock in {$store->name}: need {$qty}, have {$storeQty}";
                $result['error_type'] = 'insufficient_stock';
                $result['shortage'] = $qty - $storeQty;
                $allValid = false;
                $totalIssues++;
            }

            $validationResults[] = $result;
        }

        return response()->json([
            'success' => true,
            'all_valid' => $allValid,
            'total_items' => count($request->product_request_ids),
            'total_issues' => $totalIssues,
            'store_id' => $storeId,
            'store_name' => $store->name ?? '',
            'validation_results' => $validationResults
        ]);
    }

    /**
     * Dispense medication (single or bulk) - STRICT VALIDATION
     * Will fail the entire batch if ANY item has insufficient stock
     *
     * References:
     * - Plan Section: Phase 3.2 - Dispense Medication
     * - Related: ProductRequestController::dispense()
     * - Uses: HmoHelper::canDeliverService()
     * - Stock is deducted from selected store at dispense time
     */
    public function dispenseMedication(Request $request)
    {
        $request->validate([
            'product_request_ids' => 'required|array',
            'product_request_ids.*' => 'exists:product_requests,id',
            'store_id' => 'required|exists:stores,id'
        ]);

        $storeId = $request->store_id;
        $store = Store::find($storeId);

        // PHASE 1: Pre-validate ALL items before any changes
        $itemsToDispense = [];
        $validationErrors = [];

        foreach ($request->product_request_ids as $prId) {
            $productRequest = ProductRequest::with(['productOrServiceRequest', 'product.stock'])->find($prId);

            if (!$productRequest) {
                $validationErrors[] = [
                    'id' => $prId,
                    'error' => 'Product request not found'
                ];
                continue;
            }

            // Validate status - must be billed (status 2)
            if ($productRequest->status != 2) {
                $statusLabels = [0 => 'Pending', 1 => 'Ready', 2 => 'Billed', 3 => 'Dispensed', 4 => 'Cancelled'];
                $statusLabel = $statusLabels[$productRequest->status] ?? 'Unknown';
                $validationErrors[] = [
                    'id' => $prId,
                    'product' => $productRequest->product->name ?? 'Unknown',
                    'error' => "Cannot dispense - item is '{$statusLabel}' (must be 'Billed')"
                ];
                continue;
            }

            // Check HMO delivery requirements
            if ($productRequest->productOrServiceRequest) {
                $deliveryCheck = HmoHelper::canDeliverService($productRequest->productOrServiceRequest);
                if (!$deliveryCheck['can_deliver']) {
                    $validationErrors[] = [
                        'id' => $prId,
                        'product' => $productRequest->product->name ?? 'Unknown',
                        'error' => $deliveryCheck['reason']
                    ];
                    continue;
                }
            }

            // Get quantity to dispense
            $qty = $productRequest->qty ?? 1;

            // STRICT store stock check - no fallback to global
            $storeStock = StoreStock::where('store_id', $storeId)
                ->where('product_id', $productRequest->product_id)
                ->first();

            $availableQty = $storeStock ? $storeStock->current_quantity : 0;

            if ($availableQty < $qty) {
                $validationErrors[] = [
                    'id' => $prId,
                    'product' => $productRequest->product->name ?? 'Unknown',
                    'error' => "Insufficient stock in '{$store->name}': need {$qty}, available {$availableQty}",
                    'shortage' => $qty - $availableQty
                ];
                continue;
            }

            // Item passed validation - add to dispense list
            $itemsToDispense[] = [
                'productRequest' => $productRequest,
                'storeStock' => $storeStock,
                'qty' => $qty
            ];
        }

        // If ANY validation errors, reject the entire batch
        if (count($validationErrors) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot dispense: ' . count($validationErrors) . ' item(s) failed validation. Fix all issues before proceeding.',
                'validation_errors' => $validationErrors,
                'dispensed_count' => 0
            ], 422);
        }

        // PHASE 2: All validations passed - now execute dispense in transaction
        try {
            DB::beginTransaction();

            $dispensedCount = 0;

            foreach ($itemsToDispense as $item) {
                $productRequest = $item['productRequest'];
                $storeStock = $item['storeStock'];
                $qty = $item['qty'];

                // Deduct from store stock
                $storeStock->decrement('current_quantity', $qty);
                $storeStock->increment('quantity_sale', $qty);

                // Also deduct from global stock for consistency
                $globalStock = $productRequest->product->stock;
                if ($globalStock) {
                    $globalStock->decrement('current_quantity', $qty);
                    $globalStock->increment('quantity_sale', $qty);
                }

                // Update product request status to dispensed
                $productRequest->update([
                    'status' => 3,
                    'dispensed_by' => Auth::id(),
                    'dispense_date' => now(),
                    'dispensed_from_store_id' => $storeId
                ]);

                // Also update the ProductOrServiceRequest with store info
                if ($productRequest->productOrServiceRequest) {
                    $productRequest->productOrServiceRequest->update([
                        'dispensed_from_store_id' => $storeId
                    ]);
                }

                $dispensedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully dispensed {$dispensedCount} medication(s) from '{$store->name}'",
                'dispensed_count' => $dispensedCount,
                'store_name' => $store->name
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Pharmacy dispense error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Dispense error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get my dispensing transactions (current pharmacist)
     *
     * References:
     * - Plan Section: Tab 6 - Reports > Daily Dispensing Summary
     */
    public function getMyTransactions(Request $request)
    {
        $query = ProductRequest::with(['product.price', 'patient.user', 'productOrServiceRequest.payment'])
            ->where('dispensed_by', Auth::id())
            ->where('status', 3);

        // Apply date filters
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('dispense_date', '>=', $request->from_date);
        }
        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('dispense_date', '<=', $request->to_date);
        }

        // Apply payment type filter
        if ($request->has('payment_type') && $request->payment_type) {
            $query->whereHas('productOrServiceRequest.payment', function($q) use ($request) {
                $q->where('payment_type', $request->payment_type);
            });
        }

        // Apply bank filter
        if ($request->has('bank_id') && $request->bank_id) {
            $query->whereHas('productOrServiceRequest.payment', function($q) use ($request) {
                $q->where('bank_id', $request->bank_id);
            });
        }

        $transactions = $query->orderBy('dispense_date', 'desc')->get();

        $items = $transactions->map(function ($pr) {
            $posr = $pr->productOrServiceRequest;
            $payment = optional($posr)->payment;
            $price = optional(optional($pr->product)->price)->current_sale_price ?? 0;
            $amount = $pr->qty * $price;

            return [
                'id' => $pr->id,
                'created_at' => $pr->dispense_date ? Carbon::parse($pr->dispense_date)->format('Y-m-d H:i:s') : '',
                'patient_name' => userfullname($pr->patient->user_id),
                'file_no' => $pr->patient->file_no,
                'reference_no' => optional($payment)->reference_no,
                'payment_type' => optional($payment)->payment_type ?? 'N/A',
                'bank_name' => optional($payment)->bank ? optional($payment)->bank->name : null,
                'product_name' => optional($pr->product)->product_name,
                'quantity' => $pr->qty,
                'unit_price' => $price,
                'total' => $amount,
                'total_discount' => optional($posr)->discount ?? 0,
            ];
        });

        // Calculate statistics
        $totalAmount = $items->sum('total');
        $totalDiscount = $items->sum('total_discount');

        // Group by payment type
        $byType = $items->groupBy('payment_type')->map(function($group, $type) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('total')
            ];
        });

        $summary = [
            'count' => $items->count(),
            'total_amount' => $totalAmount,
            'total_discount' => $totalDiscount,
            'net_amount' => $totalAmount - $totalDiscount,
            'by_type' => $byType
        ];

        return response()->json([
            'transactions' => $items->values(),
            'summary' => $summary,
        ]);
    }

    /**
     * Print prescription slip for selected prescriptions
     *
     * References:
     * - Plan Section: Phase 5 - Prescription Slip Print Template
     * - Includes: Hospital branding, doctor, pharmacist, patient details
     */
    public function printPrescriptionSlip(Request $request)
    {
        $request->validate([
            'product_request_ids' => 'required|array',
            'product_request_ids.*' => 'exists:product_requests,id'
        ]);

        $prescriptions = ProductRequest::with([
            'product',
            'patient.user',
            'patient.hmo',
            'doctor',
            'dispenser',
            'encounter'
        ])
        ->whereIn('id', $request->product_request_ids)
        ->orderBy('created_at', 'asc')
        ->get();

        if ($prescriptions->isEmpty()) {
            return response()->json(['message' => 'No prescriptions found'], 404);
        }

        $patient = $prescriptions->first()->patient;
        $appsettings = appsettings();

        // Determine pharmacist name
        $pharmacist = Auth::user();
        $pharmacistName = userfullname(Auth::id());

        $data = [
            'prescriptions' => $prescriptions,
            'patient' => $patient,
            'appsettings' => $appsettings,
            'pharmacist' => $pharmacistName,
            'print_date' => Carbon::now()->format('d M Y H:i'),
        ];

        return view('admin.pharmacy.prescription_slip', $data);
    }

    /**
     * Search for products/medications
     *
     * References:
     * - Plan Section: New Request Tab - Product Search
     */
    public function searchProducts(Request $request)
    {
        $term = $request->input('term', '');
        $patientId = $request->input('patient_id', null);

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        // Get the patient's HMO if patient_id is provided
        $patient = null;
        if ($patientId) {
            $patient = Patient::find($patientId);
        }

        $products = Product::with(['category', 'price', 'stock'])
            ->where(function($q) use ($term) {
                $q->where('product_name', 'like', "%{$term}%")
                  ->orWhere('product_code', 'like', "%{$term}%");
            })
            ->where('status', 1) // Active products only
            ->orderBy('product_name')
            ->limit(20)
            ->get()
            ->map(function($product) use ($patient) {
                $basePrice = optional($product->price)->current_sale_price ?? 0;
                $stockQty = optional($product->stock)->current_quantity ?? 0;

                // Get HMO coverage info if patient has HMO
                $payableAmount = $basePrice;
                $claimsAmount = 0;
                $coverageMode = null;

                if ($patient && $patient->hmo_id) {
                    try {
                        $tariffInfo = HmoHelper::applyHmoTariff($patient->id, $product->id);
                        if ($tariffInfo) {
                            $payableAmount = $tariffInfo['payable_amount'] ?? $basePrice;
                            $claimsAmount = $tariffInfo['claims_amount'] ?? 0;
                            $coverageMode = $tariffInfo['coverage_mode'] ?? null;
                        }
                    } catch (\Exception $e) {
                        // No tariff found, use base price
                    }
                }

                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'product_code' => $product->product_code,
                    'category_name' => optional($product->category)->category_name,
                    'price' => $basePrice,
                    'stock_qty' => $stockQty,
                    'payable_amount' => $payableAmount,
                    'claims_amount' => $claimsAmount,
                    'coverage_mode' => $coverageMode,
                ];
            });

        return response()->json($products);
    }

    /**
     * Create a new prescription request from pharmacy
     *
     * This creates ONLY ProductRequest with status=1 (unbilled).
     * ProductOrServiceRequest is NOT created here - it's created when billing.
     * This matches the flow in EncounterController::savePrescriptions()
     *
     * References:
     * - Plan Section: New Request Tab - Create Prescription Request
     * - Models: ProductRequest only (ProductOrServiceRequest created on billing)
     */
    public function createPrescriptionRequest(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.qty' => 'required|integer|min:1',
            'products.*.dose' => 'nullable|string',
        ]);

        $patient = Patient::findOrFail($request->patient_id);

        DB::beginTransaction();
        try {
            $createdRequests = [];

            foreach ($request->products as $productData) {
                $product = Product::with('price')->findOrFail($productData['product_id']);

                // Create ProductRequest ONLY (status=1 means unbilled/requested)
                // This matches EncounterController::savePrescriptions() behavior
                $productRequest = ProductRequest::create([
                    'patient_id' => $patient->id,
                    'product_id' => $product->id,
                    'encounter_id' => $patient->current_encounter_id ?? null,
                    'qty' => $productData['qty'] ?? 1,
                    'dose' => $productData['dose'] ?? null,
                    'doctor_id' => Auth::id(), // Requester
                    'status' => 1, // 1 = Unbilled/Requested - ALWAYS start here
                    'created_at' => now(),
                ]);

                // NOTE: ProductOrServiceRequest is NOT created here.
                // It will be created when the prescription is BILLED via billPrescriptions()
                // This ensures items don't appear in billing waitlist until actually billed.

                $createdRequests[] = $productRequest;
            }

            DB::commit();

            Log::info('Prescription request created from pharmacy workbench', [
                'patient_id' => $patient->id,
                'user_id' => Auth::id(),
                'product_count' => count($createdRequests),
            ]);

            return response()->json([
                'success' => true,
                'message' => count($createdRequests) . ' medication(s) requested successfully. Items are now in the billing queue.',
                'requests' => $createdRequests,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create prescription request', [
                'error' => $e->getMessage(),
                'patient_id' => $patient->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create prescription request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bill prescriptions - creates ProductOrServiceRequest and updates status to 2
     *
     * This matches the flow in ProductRequestController::bill()
     * - For existing ProductRequests: Creates ProductOrServiceRequest and updates status to 2
     * - For added products: Creates both ProductRequest and ProductOrServiceRequest
     * - Decrements stock
     */
    public function billPrescriptions(Request $request)
    {
        $request->validate([
            'prescription_ids' => 'nullable|array',
            'prescription_ids.*' => 'exists:product_requests,id',
            'addedPrescBillRows' => 'nullable|array',
            'addedPrescBillRows.*' => 'exists:products,id',
            'consult_presc_dose' => 'nullable|array',
            'patient_user_id' => 'required|exists:users,id',
            'patient_id' => 'required|exists:patients,id'
        ]);

        try {
            DB::beginTransaction();

            $billedCount = 0;
            $errors = [];
            $patient = Patient::findOrFail($request->patient_id);

            // Process existing ProductRequests (from DataTable checkboxes)
            if ($request->has('prescription_ids') && is_array($request->prescription_ids)) {
                foreach ($request->prescription_ids as $prId) {
                    $productRequest = ProductRequest::with('product')->find($prId);

                    if (!$productRequest) {
                        $errors[] = "PR#{$prId}: Not found";
                        continue;
                    }

                    // Only unbilled items can be billed
                    if ($productRequest->status != 1) {
                        $errors[] = "PR#{$prId}: Already billed or dispensed";
                        continue;
                    }

                    $prodId = $productRequest->product_id;

                    // Create ProductOrServiceRequest (billing record)
                    $billReq = new ProductOrServiceRequest();
                    $billReq->user_id = $request->patient_user_id;
                    $billReq->staff_user_id = Auth::id();
                    $billReq->product_id = $prodId;

                    // Apply HMO tariff if patient has HMO
                    $this->applyHmoTariffToRequest($billReq, $patient, $prodId, $productRequest->product);

                    $billReq->save();

                    // Update ProductRequest to billed status
                    // Note: Stock is NOT deducted at billing - it will be deducted at dispense
                    $productRequest->update([
                        'status' => 2,
                        'billed_by' => Auth::id(),
                        'billed_date' => now(),
                        'product_request_id' => $billReq->id
                    ]);

                    $billedCount++;
                }
            }

            // Process newly added products (from search & add)
            if ($request->has('addedPrescBillRows') && is_array($request->addedPrescBillRows)) {
                $doses = $request->consult_presc_dose ?? [];

                for ($i = 0; $i < count($request->addedPrescBillRows); $i++) {
                    $productId = $request->addedPrescBillRows[$i];
                    $dose = $doses[$i] ?? '';

                    $product = Product::with(['price', 'stock'])->find($productId);
                    if (!$product) {
                        $errors[] = "Product #{$productId}: Not found";
                        continue;
                    }

                    // Create ProductOrServiceRequest first
                    $billReq = new ProductOrServiceRequest();
                    $billReq->user_id = $request->patient_user_id;
                    $billReq->staff_user_id = Auth::id();
                    $billReq->product_id = $productId;

                    // Apply HMO tariff
                    $this->applyHmoTariffToRequest($billReq, $patient, $productId, $product);

                    $billReq->save();

                    // Create ProductRequest with status=2 (billed)
                    // Note: Stock is NOT deducted at billing - it will be deducted at dispense
                    $productRequest = new ProductRequest();
                    $productRequest->product_id = $productId;
                    $productRequest->dose = $dose;
                    $productRequest->qty = 1;
                    $productRequest->billed_by = Auth::id();
                    $productRequest->billed_date = now();
                    $productRequest->patient_id = $patient->id;
                    $productRequest->doctor_id = Auth::id();
                    $productRequest->product_request_id = $billReq->id;
                    $productRequest->status = 2; // Billed
                    $productRequest->save();

                    $billedCount++;
                }
            }

            DB::commit();

            $message = "Successfully billed {$billedCount} prescription(s)";
            if (count($errors) > 0) {
                $message .= ". Errors: " . implode('; ', $errors);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'billed_count' => $billedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bill prescriptions error: ' . $e->getMessage() . ' at line ' . $e->getLine());
            return response()->json(['message' => 'Failed to bill prescriptions: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper to apply HMO tariff to a ProductOrServiceRequest
     */
    private function applyHmoTariffToRequest(ProductOrServiceRequest $billReq, Patient $patient, $productId, $product = null)
    {
        try {
            if ($patient->hmo_id) {
                $hmoData = HmoHelper::applyHmoTariff($patient->id, $productId, null);
                if ($hmoData) {
                    $billReq->payable_amount = $hmoData['payable_amount'];
                    $billReq->claims_amount = $hmoData['claims_amount'];
                    $billReq->coverage_mode = $hmoData['coverage_mode'];
                    $billReq->validation_status = $hmoData['validation_status'] ?? 'pending';
                    return;
                }
            }
        } catch (\Exception $e) {
            Log::warning('HMO tariff lookup failed', [
                'patient_id' => $patient->id,
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback: No HMO or tariff lookup failed - use product price
        if (!$product) {
            $product = Product::with('price')->find($productId);
        }
        $price = optional(optional($product)->price)->current_sale_price ?? 0;
        $billReq->payable_amount = $price;
        $billReq->claims_amount = 0;
        $billReq->coverage_mode = 'none';
    }

    /**
     * Record billing for prescriptions (mark as billed - status 1 to 2)
     * @deprecated Use billPrescriptions() instead
     */
    public function recordBilling(Request $request)
    {
        // Redirect to the new billPrescriptions method
        return $this->billPrescriptions($request);
    }

    /**
     * Dismiss prescriptions (soft delete or cancel)
     */
    public function dismissPrescriptions(Request $request)
    {
        $request->validate([
            'prescription_ids' => 'required|array',
            'prescription_ids.*' => 'exists:product_requests,id'
        ]);

        try {
            DB::beginTransaction();

            $dismissedCount = 0;
            $errors = [];

            foreach ($request->prescription_ids as $prId) {
                $productRequest = ProductRequest::find($prId);

                // Can't dismiss already dispensed items
                if ($productRequest->status == 3) {
                    $errors[] = "PR#{$prId}: Already dispensed - cannot dismiss";
                    continue;
                }

                // Soft delete or mark as cancelled
                $productRequest->delete(); // Soft delete if using SoftDeletes trait

                $dismissedCount++;
            }

            DB::commit();

            $message = "Successfully dismissed {$dismissedCount} prescription(s)";
            if (count($errors) > 0) {
                $message .= ". Errors: " . implode('; ', $errors);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'dismissed_count' => $dismissedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Dismiss prescriptions error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to dismiss prescriptions: ' . $e->getMessage()], 500);
        }
    }

    // ============================================
    // PHARMACY REPORTS & ANALYTICS ENDPOINTS
    // ============================================

    /**
     * Get list of pharmacists (staff with pharmacy role)
     */
    public function getPharmacists()
    {
        $pharmacists = User::where(function($q) {
                $q->whereHas('roles', function ($query) {
                    $query->whereIn('name', ['pharmacist', 'pharmacy', 'pharmacy-staff']);
                })
                ->orWhere('is_admin', 23); // 23 = Pharmacist in user_categories
            })
            ->select('id', 'surname', 'firstname')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => trim($user->surname . ' ' . $user->firstname)
                ];
            })
            ->sortBy('name')
            ->values();

        return response()->json($pharmacists);
    }

    /**
     * Get list of HMOs for filter dropdown
     */
    public function getHmosForFilter()
    {
        $hmos = Hmo::select('id', 'name', 'hmo_scheme_id')
            ->with('scheme:id,name')
            ->orderBy('name')
            ->get()
            ->groupBy(function ($hmo) {
                return $hmo->scheme ? $hmo->scheme->name : 'Others';
            })
            ->map(function ($group) {
                return $group->map(function ($hmo) {
                    return ['id' => $hmo->id, 'name' => $hmo->name];
                });
            });

        return response()->json($hmos);
    }

    /**
     * Get list of doctors for filter dropdown
     */
    public function getDoctorsForFilter()
    {
        $doctors = User::where(function($q) {
                $q->whereHas('roles', function ($query) {
                    $query->whereIn('name', ['doctor', 'Doctor', 'physician', 'consultant']);
                })
                ->orWhere('is_admin', 21); // 21 = Doctor in user_categories
            })
            ->select('id', 'surname', 'firstname')
            ->get()
            ->map(function($doctor) {
                return [
                    'id' => $doctor->id,
                    'name' => trim($doctor->surname . ' ' . $doctor->firstname)
                ];
            })
            ->sortBy('name')
            ->values();

        return response()->json($doctors);
    }

    /**
     * Get list of product categories for filter dropdown
     */
    public function getProductCategories()
    {
        $categories = ProductCategory::where('status', 1)
            ->select('id', 'category_name as name')
            ->orderBy('category_name')
            ->get();

        return response()->json($categories);
    }

    /**
     * Get summary statistics for pharmacy reports
     */
    public function getReportStatistics(Request $request)
    {
        $query = ProductRequest::query();
        $this->applyReportFilters($query, $request);

        // Total dispensed
        $totalDispensed = (clone $query)->where('status', 3)->count();

        // Revenue calculations
        $revenueQuery = (clone $query)->where('status', 3);

        // Join with product_or_service_requests for payment info
        $revenueData = DB::table('product_requests as pr')
            ->join('product_or_service_requests as posr', 'pr.product_request_id', '=', 'posr.id')
            ->leftJoin('payments as pay', 'posr.payment_id', '=', 'pay.id')
            ->leftJoin('products as p', 'pr.product_id', '=', 'p.id')
            ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
            ->where('pr.status', 3)
            ->when($request->date_from, fn($q) => $q->whereDate('pr.updated_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('pr.updated_at', '<=', $request->date_to))
            ->when($request->store_id, fn($q) => $q->where('pr.dispensed_from_store_id', $request->store_id))
            ->when($request->hmo_id, fn($q) => $q->where('pay.hmo_id', $request->hmo_id))
            ->when($request->pharmacist_id, fn($q) => $q->where('pr.dispensed_by', $request->pharmacist_id))
            ->selectRaw('
                COALESCE(SUM(pr.qty * COALESCE(pp.current_sale_price, 0)), 0) as total_revenue,
                COALESCE(SUM(CASE WHEN pay.payment_type IN ("CASH", "cash") THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END), 0) as cash_sales,
                COALESCE(SUM(CASE WHEN pay.payment_type IN ("HMO", "hmo") OR pay.hmo_id IS NOT NULL THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END), 0) as hmo_claims
            ')
            ->first();

        // Unique patients
        $uniquePatients = (clone $query)->where('status', 3)->distinct('patient_id')->count('patient_id');

        // Pending count
        $pendingCount = ProductRequest::whereBetween('status', [1, 2])
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->count();

        // Trend data (last 7 days or filter range)
        $trendData = $this->getTrendData($request);

        // Revenue breakdown by payment type
        $revenueBreakdown = $this->getRevenueBreakdownData($request);

        return response()->json([
            'total_dispensed' => $totalDispensed,
            'total_revenue' => $revenueData->total_revenue ?? 0,
            'cash_sales' => $revenueData->cash_sales ?? 0,
            'hmo_claims' => $revenueData->hmo_claims ?? 0,
            'unique_patients' => $uniquePatients,
            'pending_count' => $pendingCount,
            'trend_data' => $trendData,
            'revenue_breakdown' => $revenueBreakdown
        ]);
    }

    /**
     * Get dispensing report data for DataTables
     */
    public function getDispensingReport(Request $request)
    {
        $query = DB::table('product_requests as pr')
            ->join('patients as pat', 'pr.patient_id', '=', 'pat.id')
            ->leftJoin('users as pat_user', 'pat.user_id', '=', 'pat_user.id')
            ->leftJoin('products as p', 'pr.product_id', '=', 'p.id')
            ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
            ->leftJoin('product_or_service_requests as posr', 'pr.product_request_id', '=', 'posr.id')
            ->leftJoin('payments as pay', 'posr.payment_id', '=', 'pay.id')
            ->leftJoin('stores as s', 'pr.dispensed_from_store_id', '=', 's.id')
            ->leftJoin('users as u', 'pr.dispensed_by', '=', 'u.id')
            ->where('pr.status', 3)
            ->whereNull('pr.deleted_at')
            ->select([
                'pr.id',
                'pr.updated_at as dispensed_at',
                'pay.reference_no',
                DB::raw("CONCAT(pat_user.surname, ' ', pat_user.firstname) as patient_name"),
                'p.product_name',
                'pr.qty as quantity',
                DB::raw('(pr.qty * COALESCE(pp.current_sale_price, 0)) as amount'),
                'pay.payment_type as payment_type',
                's.store_name',
                DB::raw("CONCAT(u.surname, ' ', u.firstname) as pharmacist_name")
            ]);

        // Apply filters
        if ($request->date_from) {
            $query->whereDate('pr.updated_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('pr.updated_at', '<=', $request->date_to);
        }
        if ($request->store_id) {
            $query->where('pr.dispensed_from_store_id', $request->store_id);
        }
        if ($request->payment_type) {
            $query->where('pay.payment_type', $request->payment_type);
        }
        if ($request->pharmacist_id) {
            $query->where('pr.dispensed_by', $request->pharmacist_id);
        }
        if ($request->hmo_id) {
            $query->where('pay.hmo_id', $request->hmo_id);
        }
        if ($request->patient_search) {
            $search = $request->patient_search;
            $query->where(function ($q) use ($search) {
                $q->where('pat.first_name', 'LIKE', "%{$search}%")
                  ->orWhere('pat.last_name', 'LIKE', "%{$search}%")
                  ->orWhere('pat.file_no', 'LIKE', "%{$search}%");
            });
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->make(true);
    }

    /**
     * Get revenue report grouped by period
     */
    public function getRevenueReport(Request $request)
    {
        $groupBy = $request->group_by ?? 'daily';

        // Build the date grouping expression
        $dateGroup = match ($groupBy) {
            'weekly' => "DATE_FORMAT(pr.updated_at, '%Y-W%u')",
            'monthly' => "DATE_FORMAT(pr.updated_at, '%Y-%m')",
            default => "DATE(pr.updated_at)"
        };

        $query = DB::table('product_requests as pr')
            ->join('product_or_service_requests as posr', 'pr.product_request_id', '=', 'posr.id')
            ->leftJoin('payments as pay', 'posr.payment_id', '=', 'pay.id')
            ->leftJoin('products as p', 'pr.product_id', '=', 'p.id')
            ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
            ->where('pr.status', 3)
            ->whereNull('pr.deleted_at');

        if ($request->date_from) {
            $query->whereDate('pr.updated_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('pr.updated_at', '<=', $request->date_to);
        }
        if ($request->store_id) {
            $query->where('pr.dispensed_from_store_id', $request->store_id);
        }

        $data = $query->selectRaw("
                {$dateGroup} as period,
                COUNT(DISTINCT pr.id) as transactions,
                SUM(CASE WHEN pay.payment_type = 'CASH' THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as cash,
                SUM(CASE WHEN pay.payment_type = 'CARD' THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as card,
                SUM(CASE WHEN pay.payment_type = 'TRANSFER' THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as transfer,
                SUM(CASE WHEN pay.payment_type = 'HMO' OR pay.hmo_id IS NOT NULL THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as hmo,
                SUM(pr.qty * COALESCE(pp.current_sale_price, 0)) as total,
                AVG(pr.qty * COALESCE(pp.current_sale_price, 0)) as avg_transaction
            ")
            ->groupBy(DB::raw($dateGroup))
            ->orderBy('period', 'desc')
            ->get();

        // Calculate totals
        $totals = [
            'transactions' => $data->sum('transactions'),
            'cash' => $data->sum('cash'),
            'card' => $data->sum('card'),
            'transfer' => $data->sum('transfer'),
            'hmo' => $data->sum('hmo'),
            'total' => $data->sum('total'),
            'avg_transaction' => $data->count() > 0 ? $data->sum('total') / max($data->sum('transactions'), 1) : 0
        ];

        return response()->json([
            'data' => $data,
            'totals' => $totals
        ]);
    }

    /**
     * Get stock report with store breakdown
     */
    public function getStockReport(Request $request)
    {
        $query = DB::table('products as p')
            ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
            ->leftJoin('stocks as st', 'p.id', '=', 'st.product_id')
            ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
            ->where('p.status', 1)
            ->select([
                'p.id',
                'p.product_name',
                'p.product_code',
                'pc.category_name',
                'p.reorder_alert as reorder_level',
                DB::raw('COALESCE(st.current_quantity, 0) as global_stock'),
                DB::raw('COALESCE(pp.current_sale_price, 0) as unit_price'),
                DB::raw('COALESCE(st.current_quantity, 0) * COALESCE(pp.current_sale_price, 0) as stock_value')
            ]);

        if ($request->category_id) {
            $query->where('p.category_id', $request->category_id);
        }

        if ($request->low_stock_only) {
            $query->whereRaw('COALESCE(st.current_quantity, 0) <= COALESCE(p.reorder_alert, 0)');
        }

        // Get dispensed quantity for the period
        $products = $query->get();

        // Add store breakdown and dispensed qty for each product
        $products = $products->map(function ($product) use ($request) {
            // Get store breakdown
            $storeBreakdown = StoreStock::where('product_id', $product->id)
                ->join('stores', 'store_stocks.store_id', '=', 'stores.id')
                ->select('stores.store_name', 'store_stocks.current_quantity as quantity')
                ->get()
                ->map(function ($s) use ($product) {
                    $s->reorder_level = $product->reorder_level ?? 0;
                    return $s;
                });

            // Get dispensed quantity in period
            $dispensedQty = ProductRequest::where('product_id', $product->id)
                ->where('status', 3)
                ->when($request->date_from, fn($q) => $q->whereDate('updated_at', '>=', $request->date_from))
                ->when($request->date_to, fn($q) => $q->whereDate('updated_at', '<=', $request->date_to))
                ->sum('qty');

            $product->store_breakdown = $storeBreakdown;
            $product->dispensed_qty = $dispensedQty;

            return $product;
        });

        // Apply store filter after getting breakdown
        if ($request->store_id) {
            $storeId = $request->store_id;
            $products = $products->filter(function ($p) use ($storeId) {
                return $p->store_breakdown->contains('store_id', $storeId);
            });
        }

        return DataTables::of($products)
            ->addIndexColumn()
            ->make(true);
    }

    /**
     * Get pharmacist performance report
     */
    public function getPerformanceReport(Request $request)
    {
        $query = DB::table('product_requests as pr')
            ->join('users as u', 'pr.dispensed_by', '=', 'u.id')
            ->leftJoin('product_or_service_requests as posr', 'pr.product_request_id', '=', 'posr.id')
            ->leftJoin('payments as pay', 'posr.payment_id', '=', 'pay.id')
            ->leftJoin('products as p', 'pr.product_id', '=', 'p.id')
            ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
            ->where('pr.status', 3)
            ->whereNull('pr.deleted_at')
            ->whereNotNull('pr.dispensed_by');

        if ($request->date_from) {
            $query->whereDate('pr.updated_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('pr.updated_at', '<=', $request->date_to);
        }

        $data = $query->selectRaw("
                u.id as pharmacist_id,
                CONCAT(u.surname, ' ', u.firstname) as pharmacist_name,
                COUNT(pr.id) as total_dispensed,
                SUM(pr.qty * COALESCE(pp.current_sale_price, 0)) as total_revenue,
                SUM(CASE WHEN pay.payment_type = 'CASH' THEN 1 ELSE 0 END) as cash_transactions,
                SUM(CASE WHEN pay.payment_type = 'HMO' OR pay.hmo_id IS NOT NULL THEN 1 ELSE 0 END) as hmo_transactions,
                SUM(CASE WHEN pay.payment_type = 'CASH' THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as cash_amount,
                SUM(CASE WHEN pay.payment_type = 'HMO' OR pay.hmo_id IS NOT NULL THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as hmo_amount,
                AVG(TIMESTAMPDIFF(MINUTE, pr.created_at, pr.updated_at)) as avg_tat,
                COUNT(DISTINCT pr.patient_id) as unique_patients
            ")
            ->groupBy('u.id', 'u.surname', 'u.firstname')
            ->orderBy('total_revenue', 'desc')
            ->get();

        // Calculate totals
        $totals = [
            'total_dispensed' => $data->sum('total_dispensed'),
            'total_revenue' => $data->sum('total_revenue'),
            'cash_transactions' => $data->sum('cash_transactions'),
            'hmo_transactions' => $data->sum('hmo_transactions'),
            'cash_amount' => $data->sum('cash_amount'),
            'hmo_amount' => $data->sum('hmo_amount'),
            'avg_tat' => $data->count() > 0 ? round($data->avg('avg_tat'), 1) : null,
            'unique_patients' => $data->sum('unique_patients')
        ];

        return response()->json([
            'data' => $data,
            'totals' => $totals
        ]);
    }

    /**
     * Get HMO claims summary report
     */
    public function getHmoClaimsReport(Request $request)
    {
        $query = DB::table('product_requests as pr')
            ->join('product_or_service_requests as posr', 'pr.product_request_id', '=', 'posr.id')
            ->leftJoin('payments as pay', 'posr.payment_id', '=', 'pay.id')
            ->join('hmos as h', 'pay.hmo_id', '=', 'h.id')
            ->leftJoin('products as p', 'pr.product_id', '=', 'p.id')
            ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
            ->where('pr.status', 3)
            ->whereNull('pr.deleted_at')
            ->whereNotNull('pay.hmo_id');

        if ($request->date_from) {
            $query->whereDate('pr.updated_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('pr.updated_at', '<=', $request->date_to);
        }
        if ($request->hmo_id) {
            $query->where('pay.hmo_id', $request->hmo_id);
        }

        $data = $query->selectRaw("
                h.id as hmo_id,
                h.name as hmo_name,
                COUNT(pr.id) as total_claims,
                SUM(pr.qty * COALESCE(pp.current_sale_price, 0)) as total_amount,
                SUM(CASE WHEN posr.validation_status = 'approved' THEN 1 ELSE 0 END) as validated_count,
                SUM(CASE WHEN posr.validation_status = 'approved' THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as validated_amount,
                SUM(CASE WHEN posr.validation_status = 'pending' OR posr.validation_status IS NULL THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN posr.validation_status = 'pending' OR posr.validation_status IS NULL THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as pending_amount,
                SUM(CASE WHEN posr.validation_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN posr.validation_status = 'rejected' THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as rejected_amount
            ")
            ->groupBy('h.id', 'h.name')
            ->orderBy('total_amount', 'desc')
            ->get();

        // Calculate totals
        $totals = [
            'total_claims' => $data->sum('total_claims'),
            'total_amount' => $data->sum('total_amount'),
            'validated_count' => $data->sum('validated_count'),
            'validated_amount' => $data->sum('validated_amount'),
            'pending_count' => $data->sum('pending_count'),
            'pending_amount' => $data->sum('pending_amount'),
            'rejected_count' => $data->sum('rejected_count'),
            'rejected_amount' => $data->sum('rejected_amount')
        ];

        return response()->json([
            'data' => $data,
            'totals' => $totals
        ]);
    }

    /**
     * Get top 10 products by dispensing volume
     */
    public function getTopProducts(Request $request)
    {
        $query = DB::table('product_requests as pr')
            ->join('products as p', 'pr.product_id', '=', 'p.id')
            ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
            ->where('pr.status', 3)
            ->whereNull('pr.deleted_at');

        if ($request->date_from) {
            $query->whereDate('pr.updated_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('pr.updated_at', '<=', $request->date_to);
        }
        if ($request->store_id) {
            $query->where('pr.dispensed_from_store_id', $request->store_id);
        }

        $products = $query->selectRaw("
                p.id,
                p.product_name,
                SUM(pr.qty) as quantity,
                SUM(pr.qty * COALESCE(pp.current_sale_price, 0)) as revenue
            ")
            ->groupBy('p.id', 'p.product_name')
            ->orderBy('quantity', 'desc')
            ->limit(10)
            ->get();

        return response()->json($products);
    }

    /**
     * Get payment methods breakdown
     */
    public function getPaymentMethodsBreakdown(Request $request)
    {
        $query = DB::table('product_requests as pr')
            ->join('product_or_service_requests as posr', 'pr.product_request_id', '=', 'posr.id')
            ->leftJoin('payments as pay', 'posr.payment_id', '=', 'pay.id')
            ->leftJoin('products as p', 'pr.product_id', '=', 'p.id')
            ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
            ->where('pr.status', 3)
            ->whereNull('pr.deleted_at');

        if ($request->date_from) {
            $query->whereDate('pr.updated_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('pr.updated_at', '<=', $request->date_to);
        }
        if ($request->store_id) {
            $query->where('pr.dispensed_from_store_id', $request->store_id);
        }

        $methods = $query->selectRaw("
                COALESCE(pay.payment_type, 'Unknown') as payment_type,
                COUNT(pr.id) as count,
                SUM(pr.qty * COALESCE(pp.current_sale_price, 0)) as amount
            ")
            ->groupBy('pay.payment_type')
            ->orderBy('amount', 'desc')
            ->get();

        return response()->json($methods);
    }

    /**
     * Export reports (placeholder for Excel/PDF generation)
     */
    public function exportReports(Request $request)
    {
        // This would use Laravel Excel or DomPDF for actual exports
        // For now, return a simple CSV-style download

        $tab = $request->tab ?? 'pharm-dispensing-tab';
        $format = $request->format ?? 'excel';

        // TODO: Implement actual export logic based on tab and format
        return response()->json([
            'message' => 'Export functionality coming soon',
            'tab' => $tab,
            'format' => $format
        ]);
    }

    /**
     * Helper: Apply common report filters to query
     */
    private function applyReportFilters($query, Request $request)
    {
        if ($request->date_from) {
            $query->whereDate('updated_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('updated_at', '<=', $request->date_to);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->store_id) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->pharmacist_id) {
            $query->where('dispensed_by', $request->pharmacist_id);
        }

        return $query;
    }

    /**
     * Helper: Get trend data for charts
     */
    private function getTrendData(Request $request)
    {
        $dateFrom = $request->date_from ?? Carbon::now()->subDays(7)->format('Y-m-d');
        $dateTo = $request->date_to ?? Carbon::now()->format('Y-m-d');

        return DB::table('product_requests as pr')
            ->leftJoin('products as p', 'pr.product_id', '=', 'p.id')
            ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
            ->where('pr.status', 3)
            ->whereNull('pr.deleted_at')
            ->whereDate('pr.updated_at', '>=', $dateFrom)
            ->whereDate('pr.updated_at', '<=', $dateTo)
            ->selectRaw("
                DATE(pr.updated_at) as date,
                COUNT(pr.id) as items,
                SUM(pr.qty * COALESCE(pp.current_sale_price, 0)) as revenue
            ")
            ->groupBy(DB::raw('DATE(pr.updated_at)'))
            ->orderBy('date')
            ->get();
    }

    /**
     * Helper: Get revenue breakdown by payment type
     */
    private function getRevenueBreakdownData(Request $request)
    {
        $query = DB::table('product_requests as pr')
            ->join('product_or_service_requests as posr', 'pr.product_request_id', '=', 'posr.id')
            ->leftJoin('payments as pay', 'posr.payment_id', '=', 'pay.id')
            ->leftJoin('products as p', 'pr.product_id', '=', 'p.id')
            ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
            ->where('pr.status', 3)
            ->whereNull('pr.deleted_at');

        if ($request->date_from) {
            $query->whereDate('pr.updated_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('pr.updated_at', '<=', $request->date_to);
        }

        $breakdown = $query->selectRaw("
                SUM(CASE WHEN pay.payment_type = 'CASH' THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as cash,
                SUM(CASE WHEN pay.payment_type = 'CARD' THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as card,
                SUM(CASE WHEN pay.payment_type = 'TRANSFER' THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as transfer,
                SUM(CASE WHEN pay.payment_type = 'HMO' OR pay.hmo_id IS NOT NULL THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as hmo,
                SUM(CASE WHEN pay.payment_type = 'ACCOUNT' THEN pr.qty * COALESCE(pp.current_sale_price, 0) ELSE 0 END) as account
            ")
            ->first();

        return [
            'cash' => $breakdown->cash ?? 0,
            'card' => $breakdown->card ?? 0,
            'transfer' => $breakdown->transfer ?? 0,
            'hmo' => $breakdown->hmo ?? 0,
            'account' => $breakdown->account ?? 0
        ];
    }
}

