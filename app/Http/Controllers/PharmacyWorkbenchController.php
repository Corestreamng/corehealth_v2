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
use App\Models\StockBatch;
use App\Models\Hmo;
use App\Models\DoctorQueue;
use App\Models\AdmissionRequest;
use App\Models\Service;
use App\Models\ServiceBundleItem;
use App\Helpers\HmoHelper;
use App\Helpers\BatchHelper;
use App\Services\StockService;
use App\Services\StoreContextResolver;
use App\Models\StoreContextRule;
use Illuminate\Support\Facades\Gate;
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
 * - NEW: StockService for batch-based dispensing
 * - NEW: BatchHelper for FIFO batch selection
 */
class PharmacyWorkbenchController extends Controller
{
    /**
     * Display the pharmacy workbench main page.
     *
     * Plan §6.2 (Pharmacist Workbench), §10 (Context Resolution):
     * Resolves the pharmacy store for the current user via StoreContextResolver.
     * Passes $resolvedStore + $contextFallbackAction to drive the store context badge
     * and the "Resolve Store Context" banner in the view (Plan §6.1).
     */
    public function index()
    {
        // ── Store Governance: context resolution (Plan §10, §B3) ─────────────
        $resolver            = app(StoreContextResolver::class);
        $resolvedStore       = $resolver->resolve(auth()->user());
        $contextFallbackAction = $resolvedStore ? null : StoreContextRule::fallbackAction();

        // Candidate stores: pharmacy-type bucket + user's dept store + rule-configured stores.
        $stores = $resolver->candidateStores(auth()->user(), 'pharmacy');
        // ─────────────────────────────────────────────────────────────────────

        return view('admin.pharmacy.workbench', compact('stores', 'resolvedStore', 'contextFallbackAction'));
    }

    /**
     * Set an explicit store context override in the session (Plan §10 Step 1).
     * Requires 'store-context.change-manual' permission.
     * Shared across pharmacy, nursing, and maternity workbenches.
     *
     * Accepts an optional `context` field ('pharmacy'|'ward') to restrict
     * which store types are valid, preventing cross-context overrides.
     */
    public function setStoreContext(\Illuminate\Http\Request $request)
    {
        if (! auth()->user()->can('store-context.change-manual')) {
            return response()->json(['message' => 'You do not have permission to change the store context.'], 403);
        }

        $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'context'  => 'required|in:pharmacy,ward',
        ]);

        $store = Store::find($request->store_id);
        if (! $store || ! $store->status) {
            return response()->json(['message' => 'The selected store is inactive.'], 422);
        }

        $context  = $request->input('context');
        $resolver = app(\App\Services\StoreContextResolver::class);

        // Enforce context-appropriate store — the store must appear in the user's
        // candidate list for this workbench type (prevents cross-context exploits).
        $candidateIds = $resolver->candidateStores(auth()->user(), $context)
            ->pluck('id');

        if (! $candidateIds->contains($store->id)) {
            $label = $context === 'pharmacy' ? 'pharmacy workbench' : 'this workbench';
            return response()->json(['message' => "That store is not available for the {$label}."], 422);
        }

        $resolver->setSessionStore($request->store_id);

        return response()->json(['success' => true, 'store_name' => $store->store_name]);
    }

    /**
     * Clear the session store context override.
     */
    public function clearStoreContext()
    {
        app(\App\Services\StoreContextResolver::class)->clearSessionStore();
        return response()->json(['success' => true]);
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
        $items = ProductRequest::with([
            'product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller', 'procedureItem.procedure.service',
            'adaptedFromProduct', 'adapter', 'qtyAdjuster'
        ])
            ->where('status', 1)
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'DESC')
            ->get();

        // Batch-load tariff previews for unbilled items
        $patient = Patient::find($patientId);
        $tariffMap = [];
        if ($patient && $patient->hmo_id) {
            $productIds = $items->pluck('product_id')->unique()->filter()->values()->toArray();
            $previews = HmoHelper::batchPreviewTariffs($patient->hmo_id, $productIds);
            $tariffMap = $previews['products'];
        }

        // Precompute per-item tariff totals for unbilled rows so the billing-tab UI
        // can show accurate estimate badges before ProductOrServiceRequest is created.
        $tariffTotals = [];
        foreach ($items as $item) {
            $t = $tariffMap[$item->product_id] ?? null;
            if (!$t) {
                continue;
            }

            $qty = $item->qty ?? 1;
            $tariffTotals[$item->id] = [
                'payable_amount' => round($t['payable_amount'] * $qty, 2),
                'claims_amount' => round($t['claims_amount'] * $qty, 2),
                'coverage_mode' => $t['coverage_mode'] ?? 'none',
            ];
        }

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('id', function ($item) {
                return $item->id;
            })
            ->addColumn('product_id', function ($item) {
                return $item->product_id;
            })
            ->addColumn('product_name', function ($item) {
                return $item->item_name;
            })
            ->addColumn('product_code', function ($item) {
                return optional($item->product)->product_code ?? '';
            })
            ->addColumn('price', function ($item) {
                return optional(optional($item->product)->price)->current_sale_price ?? 0;
            })
            ->addColumn('payable_amount', function ($item) use ($tariffTotals, $patient) {
                $posr = $item->productOrServiceRequest;

                // For HMO patients, billing estimate must come from tariff lookup per item.
                // Do not trust stale pre-linked POSR cash values in unbilled stage.
                if ($patient && $patient->hmo_id && isset($tariffTotals[$item->id])) {
                    return $tariffTotals[$item->id]['payable_amount'];
                }

                if ($posr) {
                    return $posr->payable_amount ?? 0;
                }

                $cashPrice = optional(optional($item->product)->price)->current_sale_price ?? 0;
                return $cashPrice * ($item->qty ?? 1);
            })
            ->addColumn('claims_amount', function ($item) use ($tariffTotals, $patient) {
                $posr = $item->productOrServiceRequest;

                if ($patient && $patient->hmo_id && isset($tariffTotals[$item->id])) {
                    return $tariffTotals[$item->id]['claims_amount'];
                }

                if ($posr) {
                    return $posr->claims_amount ?? 0;
                }

                return $tariffTotals[$item->id]['claims_amount'] ?? 0;
            })
            ->addColumn('coverage_mode', function ($item) use ($tariffTotals, $patient) {
                $posr = $item->productOrServiceRequest;

                if ($patient && $patient->hmo_id && isset($tariffTotals[$item->id])) {
                    return $tariffTotals[$item->id]['coverage_mode'] ?? 'primary';
                }

                if ($posr && !empty($posr->coverage_mode)) {
                    return $posr->coverage_mode;
                }

                return $tariffTotals[$item->id]['coverage_mode'] ?? 'cash';
            })
            ->addColumn('is_paid', function ($item) {
                return optional($item->productOrServiceRequest)->payment_id !== null;
            })
            ->addColumn('is_validated', function ($item) {
                $status = optional($item->productOrServiceRequest)->validation_status;
                return in_array($status, ['validated', 'approved', 'awaiting_code']);
            })
            ->addColumn('qty', function ($item) {
                return $item->qty ?? 1;
            })
            ->addColumn('dose', function ($item) {
                return $item->dose ?? 'N/A';
            })
            ->addColumn('status', function ($item) {
                return $item->status;
            })
            ->addColumn('requested_by', function ($item) {
                return $item->doctor_id ? userfullname($item->doctor_id) : 'N/A';
            })
            ->addColumn('requested_at', function ($item) {
                return $item->created_at ? date('M j, Y h:i A', strtotime($item->created_at)) : '';
            })
            ->addColumn('procedure_name', function ($item) {
                $procedureItem = $item->procedureItem;
                return $procedureItem ? (optional(optional($procedureItem->procedure)->service)->service_name ?? 'Procedure') : null;
            })
            ->addColumn('is_bundled', function ($item) {
                $procedureItem = $item->procedureItem;
                return $procedureItem ? $procedureItem->is_bundled : false;
            })
            ->addColumn('tariff_preview', function ($item) use ($tariffMap, $patient) {
                if (!$patient || !$patient->hmo_id) return null;
                $t = $tariffMap[$item->product_id] ?? null;
                if (!$t) return ['no_tariff' => true];
                $qty = $item->qty ?? 1;
                return [
                    'payable_amount' => round($t['payable_amount'] * $qty, 2),
                    'claims_amount'  => round($t['claims_amount'] * $qty, 2),
                    'coverage_mode'  => $t['coverage_mode'],
                ];
            })
            ->addColumn('price_override', function ($item) {
                return $item->price_override;
            })
            ->addColumn('price_original', function ($item) {
                return $item->price_original;
            })
            ->addColumn('price_override_reason', function ($item) {
                return $item->price_override_reason;
            })
            ->addColumn('price_override_by', function ($item) {
                return $item->price_override_by ? userfullname($item->price_override_by) : null;
            })
            ->addColumn('price_override_at', function ($item) {
                return $item->price_override_at ? date('M j, Y h:i A', strtotime($item->price_override_at)) : null;
            })
            ->addColumn('global_stock', function ($item) {
                if (!$item->product_id) return 0;
                // Get stock from StockBatch (source of truth) - filtered by Hub & Satellite stores only
                return (int) \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
                    ->sum('current_qty');
            })
            ->addColumn('store_stocks', function ($item) {
                if (!$item->product_id) return [];
                // Get stock grouped by store from StockBatch - filtered by Hub & Satellite stores only
                $storeStockData = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
                    ->selectRaw('store_id, SUM(current_qty) as total_qty')
                    ->groupBy('store_id')
                    ->orderByDesc('total_qty')
                    ->get();

                $storeStocks = [];
                foreach ($storeStockData as $batch) {
                    $store = \App\Models\Store::find($batch->store_id);
                    $storeStocks[] = [
                        'store_id' => $batch->store_id,
                        'store_name' => $store ? $store->store_name : 'Unknown Store',
                        'quantity' => (int) $batch->total_qty
                    ];
                }
                return $storeStocks;
            })
            ->addColumn('adapted_from_product_name', function ($item) {
                return optional($item->adaptedFromProduct)->product_name;
            })
            ->addColumn('adapted_from_product_code', function ($item) {
                return optional($item->adaptedFromProduct)->product_code;
            })
            ->addColumn('adaptation_note', function ($item) {
                return $item->adaptation_note;
            })
            ->addColumn('adapted_at', function ($item) {
                return $item->adapted_at ? $item->adapted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('adapted_by_name', function ($item) {
                return $item->adapted_by ? userfullname($item->adapted_by) : null;
            })
            ->addColumn('qty_adjusted_from', function ($item) {
                return $item->qty_adjusted_from;
            })
            ->addColumn('qty_adjustment_reason', function ($item) {
                return $item->qty_adjustment_reason;
            })
            ->addColumn('qty_adjusted_at', function ($item) {
                return $item->qty_adjusted_at ? $item->qty_adjusted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('qty_adjusted_by_name', function ($item) {
                return $item->qty_adjusted_by ? userfullname($item->qty_adjusted_by) : null;
            })
            ->make(true);
    }

    /**
     * DataTables endpoint for prescriptions pending dispense (status=2)
     * Matches EncounterController::prescDispenseList() format
     */
    public function prescDispenseList($patientId)
    {
        $items = ProductRequest::with([
            'product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'doctor', 'biller', 'procedureItem.procedure.service',
            'adaptedFromProduct', 'adapter', 'qtyAdjuster'
        ])
            ->where('status', 2)
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'DESC')
            ->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('select', function ($item) {
                $posr = $item->productOrServiceRequest;
                $isPaid = optional(optional($posr)->payment)->payment_status === 'paid';
                $isValidated = in_array(optional($posr)->validation_status, ['validated', 'approved', 'awaiting_code']);

                // Check if bundled with procedure (is_bundled — procedure system, NOT our combo)
                $procedureItem = $item->procedureItem;
                $isBundled = $procedureItem && $procedureItem->is_bundled;

                // Procedure-bundled items can be dispensed if procedure is paid
                if ($isBundled) {
                    $procedurePosr = optional(optional($procedureItem->procedure)->productOrServiceRequest);
                    $procedurePaid = optional($procedurePosr->payment)->payment_status === 'paid';
                    $procedureValidated = in_array(optional($procedurePosr)->validation_status, ['validated', 'approved', 'awaiting_code']);
                    $canDispense = $procedurePaid || $procedureValidated;
                } else {
                    $canDispense = $isPaid || $isValidated;
                }

                $disabled = !$canDispense ? 'disabled' : '';
                $tooltip = !$canDispense ? 'title="Payment or HMO validation required"' : '';

                return "<input type='checkbox' name='selectedPrescDispenseRows[]' {$disabled} {$tooltip} value='{$item->id}' class='form-control'>";
            })
            ->editColumn('dose', function ($item) {
                $code = optional($item->product)->product_code ?? '';
                $name = $item->item_name;
                $posr = $item->productOrServiceRequest;

                $str = "<span class='badge badge-success'>[{$code}] {$name}</span>";

                // Show procedure bundle indicator
                $procedureItem = $item->procedureItem;
                if ($procedureItem && $procedureItem->is_bundled) {
                    $procedureName = optional(optional($procedureItem->procedure)->service)->service_name ?? 'Procedure';
                    $str .= "<br><span class='badge badge-purple' style='background: #6f42c1; color: #fff;'><i class='fa fa-procedures mr-1'></i> Bundled: {$procedureName}</span>";
                } elseif ($procedureItem) {
                    $procedureName = optional(optional($procedureItem->procedure)->service)->service_name ?? 'Procedure';
                    $str .= "<br><span class='badge badge-secondary'><i class='fa fa-procedures mr-1'></i> From: {$procedureName}</span>";
                }

                // Show HMO/payment status
                if ($posr) {
                    $isPaid = optional($posr->payment)->payment_status === 'paid';
                    $isValidated = in_array(optional($posr)->validation_status, ['validated', 'approved', 'awaiting_code']);
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
            ->addColumn('product_name', function ($item) {
                return $item->item_name;
            })
            ->addColumn('product_code', function ($item) {
                return optional($item->product)->product_code ?? '';
            })
            ->addColumn('price', function ($item) {
                return optional(optional($item->product)->price)->current_sale_price ?? 0;
            })
            ->addColumn('payable_amount', function ($item) {
                $posr = $item->productOrServiceRequest;
                return $posr ? ($posr->payable_amount ?? 0) : (optional(optional($item->product)->price)->current_sale_price ?? 0);
            })
            ->addColumn('claims_amount', function ($item) {
                return optional($item->productOrServiceRequest)->claims_amount ?? 0;
            })
            ->addColumn('coverage_mode', function ($item) {
                return optional($item->productOrServiceRequest)->coverage_mode ?? 'none';
            })
            ->addColumn('is_paid', function ($item) {
                return optional(optional($item->productOrServiceRequest)->payment)->payment_status === 'paid';
            })
            ->addColumn('is_validated', function ($item) {
                return optional($item->productOrServiceRequest)->validation_status === 'validated';
            })
            ->addColumn('can_dispense', function ($item) {
                $posr = $item->productOrServiceRequest;
                if (!$posr) return true;
                $isPaid = optional($posr->payment)->payment_status === 'paid';
                $isValidated = $posr->validation_status === 'validated';
                return $isPaid || $isValidated;
            })
            ->addColumn('global_stock', function ($item) {
                if (!$item->product_id) return 0;
                // Get stock from StockBatch (source of truth) - filtered by Hub & Satellite stores only
                return (int) \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
                    ->sum('current_qty');
            })
            ->addColumn('store_stocks', function ($item) {
                if (!$item->product_id) return [];
                // Get stock grouped by store from StockBatch - filtered by Hub & Satellite stores only
                $storeStockData = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
                    ->selectRaw('store_id, SUM(current_qty) as total_qty')
                    ->groupBy('store_id')
                    ->orderByDesc('total_qty')
                    ->get();

                $storeStocks = [];
                foreach ($storeStockData as $batch) {
                    $store = \App\Models\Store::find($batch->store_id);
                    $storeStocks[] = [
                        'store_id' => $batch->store_id,
                        'store_name' => $store ? $store->store_name : 'Unknown Store',
                        'quantity' => (int) $batch->total_qty
                    ];
                }
                return $storeStocks;
            })
            ->addColumn('adapted_from_product_name', function ($item) {
                return optional($item->adaptedFromProduct)->product_name;
            })
            ->addColumn('adapted_from_product_code', function ($item) {
                return optional($item->adaptedFromProduct)->product_code;
            })
            ->addColumn('adaptation_note', function ($item) {
                return $item->adaptation_note;
            })
            ->addColumn('adapted_at', function ($item) {
                return $item->adapted_at ? $item->adapted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('adapted_by_name', function ($item) {
                return $item->adapted_by ? userfullname($item->adapted_by) : null;
            })
            ->addColumn('qty_adjusted_from', function ($item) {
                return $item->qty_adjusted_from;
            })
            ->addColumn('qty_adjustment_reason', function ($item) {
                return $item->qty_adjustment_reason;
            })
            ->addColumn('qty_adjusted_at', function ($item) {
                return $item->qty_adjusted_at ? $item->qty_adjusted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('qty_adjusted_by_name', function ($item) {
                return $item->qty_adjusted_by ? userfullname($item->qty_adjusted_by) : null;
            })
            ->rawColumns(['select', 'dose', 'created_at'])
            ->make(true);
    }

    /**
     * DataTables endpoint for dispensed prescriptions history (status=3)
     */
    public function prescHistoryList($patientId)
    {
        $items = ProductRequest::with([
            'product.price', 'product.category', 'encounter', 'patient', 'productOrServiceRequest.payment', 'productOrServiceRequest.parent.service', 'productOrServiceRequest.parent.children.service', 'productOrServiceRequest.parent.children.product', 'doctor', 'biller', 'dispenser', 'dispensedFromBatch', 'dispensedFromStore',
            'adaptedFromProduct', 'adapter', 'qtyAdjuster'
        ])
            ->where('status', 3)
            ->where('patient_id', $patientId)
            ->where(function ($query) {
                $query->whereNull('product_request_id')
                    ->orWhereHas('productOrServiceRequest', function ($posr) {
                        $posr->whereNull('removed_at');
                    });
            })
            ->orderBy('dispense_date', 'DESC')
            ->get();

        return DataTables::of($items)
            ->addIndexColumn()
            ->addColumn('product_name', function($item) {
                return $item->item_name;
            })
            ->addColumn('product_code', function($item) {
                return optional($item->product)->product_code ?? '';
            })
            ->addColumn('requested_by', function($item) {
                return $item->doctor_id ? userfullname($item->doctor_id) : 'N/A';
            })
            ->addColumn('requested_at', function($item) {
                return $item->created_at ? date('h:i a D M j, Y', strtotime($item->created_at)) : '';
            })
            ->addColumn('billed_by', function($item) {
                return $item->billed_by ? userfullname($item->billed_by) : null;
            })
            ->addColumn('billed_at', function($item) {
                return $item->billed_date ? date('h:i a D M j, Y', strtotime($item->billed_date)) : '';
            })
            ->addColumn('dispensed_by', function($item) {
                return $item->dispensed_by ? userfullname($item->dispensed_by) : null;
            })
            ->addColumn('dispensed_at', function($item) {
                return $item->dispense_date ? date('h:i a D M j, Y', strtotime($item->dispense_date)) : '';
            })
            ->addColumn('payable_amount', function($item) {
                return optional($item->productOrServiceRequest)->payable_amount ?? 0;
            })
            ->addColumn('claims_amount', function($item) {
                return optional($item->productOrServiceRequest)->claims_amount ?? 0;
            })
            ->addColumn('is_paid', function($item) {
                return optional(optional($item->productOrServiceRequest)->payment)->status >= 1;
            })
            ->addColumn('batch_number', function($item) {
                return optional($item->dispensedFromBatch)->batch_number ?? null;
            })
            ->addColumn('batch_expiry', function($item) {
                $batch = $item->dispensedFromBatch;
                return $batch && $batch->expiry_date ? date('M Y', strtotime($batch->expiry_date)) : null;
            })
            ->addColumn('dispensed_from_store_name', function($item) {
                return optional($item->dispensedFromStore)->store_name ?? null;
            })
            ->editColumn('dose', function ($item) {
                $code = optional($item->product)->product_code ?? '';
                $name = $item->item_name;
                $posr = $item->productOrServiceRequest;

                $str = "<span class='badge badge-success'>[{$code}] {$name}</span>";
                $str .= '<hr><b>Dose/Freq:</b> ' . ($item->dose ?? 'N/A');
                $str .= '<br><b>Qty:</b> ' . ($item->qty ?? 1);

                if ($posr) {
                    $str .= '<br><small>Amount: ₦' . number_format($posr->payable_amount ?? 0, 2) . '</small>';
                }

                // Show batch info if available
                if ($item->dispensedFromBatch) {
                    $batch = $item->dispensedFromBatch;
                    $expiry = $batch->expiry_date ? date('M Y', strtotime($batch->expiry_date)) : 'N/A';
                    $str .= '<br><small class="text-info"><i class="mdi mdi-tag-outline"></i> Batch: ' . $batch->batch_number . ' (Exp: ' . $expiry . ')</small>';
                }

                // Show store if available
                if ($item->dispensedFromStore) {
                    $str .= '<br><small class="text-secondary"><i class="mdi mdi-store"></i> From: ' . $item->dispensedFromStore->store_name . '</small>';
                }

                // Bundle info block (View only — items are dispensed/paid)
                if ($posr && $posr->is_bundle_item && $posr->parent_id) {
                    $parentReq = $posr->parent;
                    if ($parentReq) {
                        $bName    = optional($parentReq->service)->service_name ?? 'Combo';
                        $bPay     = $parentReq->payable_amount ?? 0;
                        $bClaims  = $parentReq->claims_amount ?? 0;
                        $bChildren = $parentReq->children->map(function ($c) {
                            return ['name' => optional($c->service)->service_name ?? optional($c->product)->product_name ?? 'Item', 'qty' => $c->qty ?? 1, 'price' => $c->payable_amount ?? $c->amount ?? 0];
                        })->values()->toArray();
                        $bDataJson = htmlspecialchars(json_encode(['name' => $bName, 'payable_amount' => $bPay, 'claims_amount' => $bClaims, 'items' => $bChildren]), ENT_QUOTES);
                        $bNameEsc  = htmlspecialchars($bName, ENT_QUOTES);
                        $str .= "<div class='bundle-info-block mt-1 p-2 bg-light rounded'>";
                        $str .= "<small class='text-muted d-block mb-1'><i class='mdi mdi-link-variant'></i> <strong>Combo: {$bNameEsc}</strong> &mdash; &#8358;" . number_format($bPay, 2) . " patient / &#8358;" . number_format($bClaims, 2) . " claims</small>";
                        $str .= "<button type='button' class='btn btn-outline-primary btn-sm' onclick='window.BundleViewModal && BundleViewModal.show({$bDataJson})' title='View combo details'><i class='fa fa-info-circle'></i> View Combo</button>";
                        $str .= "</div>";
                    }
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
            ->addColumn('price', function ($item) {
                return optional(optional($item->product)->price)->current_sale_price ?? 0;
            })
            ->addColumn('coverage_mode', function ($item) {
                return optional($item->productOrServiceRequest)->coverage_mode ?? 'none';
            })
            ->addColumn('is_validated', function ($item) {
                return optional($item->productOrServiceRequest)->validation_status === 'validated';
            })
            ->addColumn('can_dispense', function ($item) {
                return true;
            })
            ->addColumn('global_stock', function ($item) {
                if (!$item->product_id) return 0;
                // Get stock from StockBatch (source of truth) - filtered by Hub & Satellite stores only
                return (int) \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
                    ->sum('current_qty');
            })
            ->addColumn('store_stocks', function ($item) {
                if (!$item->product_id) return [];
                // Get stock grouped by store from StockBatch - filtered by Hub & Satellite stores only
                $storeStockData = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
                    ->selectRaw('store_id, SUM(current_qty) as total_qty')
                    ->groupBy('store_id')
                    ->orderByDesc('total_qty')
                    ->get();

                $storeStocks = [];
                foreach ($storeStockData as $batch) {
                    $store = \App\Models\Store::find($batch->store_id);
                    $storeStocks[] = [
                        'store_id' => $batch->store_id,
                        'store_name' => $store ? $store->store_name : 'Unknown Store',
                        'quantity' => (int) $batch->total_qty
                    ];
                }
                return $storeStocks;
            })
            ->addColumn('adapted_from_product_name', function ($item) {
                return optional($item->adaptedFromProduct)->product_name;
            })
            ->addColumn('adapted_from_product_code', function ($item) {
                return optional($item->adaptedFromProduct)->product_code;
            })
            ->addColumn('adaptation_note', function ($item) {
                return $item->adaptation_note;
            })
            ->addColumn('adapted_at', function ($item) {
                return $item->adapted_at ? $item->adapted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('adapted_by_name', function ($item) {
                return $item->adapted_by ? userfullname($item->adapted_by) : null;
            })
            ->addColumn('qty_adjusted_from', function ($item) {
                return $item->qty_adjusted_from;
            })
            ->addColumn('qty_adjustment_reason', function ($item) {
                return $item->qty_adjustment_reason;
            })
            ->addColumn('qty_adjusted_at', function ($item) {
                return $item->qty_adjusted_at ? $item->qty_adjusted_at->format('M j, Y h:i A') : null;
            })
            ->addColumn('qty_adjusted_by_name', function ($item) {
                return $item->qty_adjusted_by ? userfullname($item->qty_adjusted_by) : null;
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
            ->searchByTerm($term)
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
                'photo' => $patient->user && $patient->user->filename ? asset('storage/image/user/' . $patient->user->filename) : asset('assets/images/default-avatar.png'),
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

        $query->where(function ($subQuery) {
            $subQuery->whereNull('product_request_id')
                ->orWhereHas('productOrServiceRequest', function ($posr) {
                    $posr->whereNull('removed_at');
                });
        });

        // Apply filters
        if ($filter === 'freeform') {
            $query->where('is_free_form', 1);
        } else {
            // Standard queues shouldn't include free-form items
            $query->where(function ($q) {
                $q->whereNull('is_free_form')->orWhere('is_free_form', 0);
            });

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

        // Detect emergency patients
        $patientIds = $patients->pluck('id')->toArray();
        $emergencyPatientIds = collect();
        if (!empty($patientIds)) {
            $emergencyFromQueue = DoctorQueue::where('priority', 'emergency')
                ->whereIn('patient_id', $patientIds)
                ->whereIn('status', [1, 2, 3])
                ->pluck('patient_id');
            $emergencyFromAdmission = AdmissionRequest::where('priority', 'emergency')
                ->whereIn('patient_id', $patientIds)
                ->where('discharged', 0)
                ->pluck('patient_id');
            $emergencyPatientIds = $emergencyFromQueue->merge($emergencyFromAdmission)->unique();
        }

        $queue = $results->map(function ($item) use ($patients, $emergencyPatientIds) {
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
                'is_emergency' => $emergencyPatientIds->contains($patient->id),
            ];
        })->filter();

        // Sort emergency patients first
        $queue = $queue->sortByDesc('is_emergency')->values();

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
            ->where(function($q) {
                $q->whereNull('is_free_form')->orWhere('is_free_form', 0);
            })
            ->select('patient_id')
            ->distinct()
            ->count();

        $unbilledCount = ProductRequest::where('status', 1)
            ->where(function($q) {
                $q->whereNull('is_free_form')->orWhere('is_free_form', 0);
            })
            ->select('patient_id')
            ->distinct()
            ->count();

        $readyCount = ProductRequest::where('status', 2)
            ->where(function($q) {
                $q->whereNull('is_free_form')->orWhere('is_free_form', 0);
            })
            ->select('patient_id')
            ->distinct()
            ->count();

        $hmoCount = ProductRequest::whereIn('status', [1, 2])
            ->where(function($q) {
                $q->whereNull('is_free_form')->orWhere('is_free_form', 0);
            })
            ->whereHas('productOrServiceRequest', function($q) {
                $q->where('claims_amount', '>', 0);
            })
            ->select('patient_id')
            ->distinct()
            ->count();

        $freeformCount = ProductRequest::where('is_free_form', 1)
            ->select('patient_id')
            ->distinct()
            ->count();

        return response()->json([
            'total' => $totalCount,
            'unbilled' => $unbilledCount,
            'ready' => $readyCount,
            'hmo' => $hmoCount,
            'freeform' => $freeformCount,
            'emergency' => $this->getEmergencyPharmacyCount(),
        ]);
    }

    /**
     * Count patients with pending prescriptions who are emergency patients
     */
    private function getEmergencyPharmacyCount()
    {
        $pendingPatientIds = ProductRequest::whereIn('status', [1, 2])
            ->distinct()
            ->pluck('patient_id');

        $fromQueue = DoctorQueue::where('priority', 'emergency')
            ->whereIn('patient_id', $pendingPatientIds)
            ->whereIn('status', [1, 2, 3])
            ->distinct()
            ->pluck('patient_id');

        $fromAdmission = AdmissionRequest::where('priority', 'emergency')
            ->whereIn('patient_id', $pendingPatientIds)
            ->where('discharged', 0)
            ->distinct()
            ->pluck('patient_id');

        return $fromQueue->merge($fromAdmission)->unique()->count();
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
            'productOrServiceRequest.payment',
            'productOrServiceRequest.parent.payment',
            'adaptedFromProduct',
            'adapter',
            'qtyAdjuster'
            ])
            ->where('patient_id', $patientId)
            ->where(function ($subQuery) {
                $subQuery->whereNull('product_request_id')
                    ->orWhereHas('productOrServiceRequest', function ($posr) {
                        $posr->whereNull('removed_at');
                    });
            })
            ->whereIn('status', [1, 2]); // Requested or Billed

        // Apply status filter if provided
        if ($statusFilter === 'freeform') {
            $query->where('is_free_form', 1);
        } else {
            // Exclude free-form from normal tabs
            $query->where(function ($q) {
                $q->whereNull('is_free_form')->orWhere('is_free_form', 0);
            });

            if ($statusFilter === 'unbilled') {
                $query->where('status', 1);
            } elseif ($statusFilter === 'billed') {
                // Billed but not yet paid/validated for HMO
                $query->where('status', 2)
                      ->where(function($q) {
                          $q->where(function ($own) {
                              $own->whereHas('productOrServiceRequest', function ($sq) {
                                  $sq->whereNull('payment_id')
                                     ->where(function ($v) {
                                         $v->whereNull('validation_status')
                                           ->orWhereNotIn('validation_status', ['validated', 'approved', 'awaiting_code']);
                                     });
                              });
                          })->where(function ($parentState) {
                              // For combo child rows, treat parent payment/validation as readiness signal.
                              $parentState->whereDoesntHave('productOrServiceRequest.parent')
                                  ->orWhereHas('productOrServiceRequest.parent', function ($parent) {
                                      $parent->whereNull('payment_id')
                                          ->where(function ($v) {
                                              $v->whereNull('validation_status')
                                                ->orWhereNotIn('validation_status', ['validated', 'approved', 'awaiting_code']);
                                          });
                                  });
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
                                $sq->whereIn('validation_status', ['validated', 'approved', 'awaiting_code']);
                            })
                            ->orWhereHas('productOrServiceRequest.parent', function($sq) {
                                $sq->whereNotNull('payment_id');
                            })
                            ->orWhereHas('productOrServiceRequest.parent', function($sq) {
                                $sq->whereIn('validation_status', ['validated', 'approved', 'awaiting_code']);
                            });
                      });
            }
        }

        $records = $query->orderBy('created_at', 'desc')->get();

        // ── Performance: Batch stock lookup for all products ──
        $productIds = $records->pluck('product.id')->filter()->unique()->values();
        $batchStocks = collect();
        if ($productIds->isNotEmpty()) {
            // Get all stocks for all required products in one query, joined with stores
            $batchStocks = \App\Models\StockBatch::whereIn('product_id', $productIds)
                ->where('current_qty', '>', 0)
                ->join('stores', 'stock_batches.store_id', '=', 'stores.id')
                ->whereIn('stores.distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE])
                ->selectRaw('stock_batches.product_id, stock_batches.store_id, stores.store_name, SUM(stock_batches.current_qty) as total_qty')
                ->groupBy('stock_batches.product_id', 'stock_batches.store_id', 'stores.store_name')
                ->get()
                ->groupBy('product_id');
        }

        $items = $records->map(function ($pr) use ($batchStocks) {
                $basePrice = optional(optional($pr->product)->price)->current_sale_price ?? 0;
                $posr = $pr->productOrServiceRequest;
                $payment = optional($posr)->payment;
                $parentPosr = optional($posr)->parent;

                // Determine ready status
                $isPaid = !is_null(optional($posr)->payment_id);
                $isValidated = in_array(optional($posr)->validation_status, ['validated', 'approved', 'awaiting_code']);
                $isParentPaid = !is_null(optional($parentPosr)->payment_id);
                $isParentValidated = in_array(optional($parentPosr)->validation_status, ['validated', 'approved', 'awaiting_code']);
                $isReady = $isPaid || $isValidated || $isParentPaid || $isParentValidated;

                // Status label logic
                $statusLabel = $pr->status == 1 ? 'Unbilled' : 'Billed';
                if ($pr->status == 2 && $isReady) {
                    $statusLabel = 'Ready to Dispense';
                }

                // Get stock information from pre-loaded batches
                $globalStock = 0;
                $storeStocks = [];
                if ($pr->product && $batchStocks->has($pr->product->id)) {
                    $productStocks = $batchStocks->get($pr->product->id);
                    
                    foreach ($productStocks as $batch) {
                        $qty = (int) $batch->total_qty;
                        $globalStock += $qty;
                        $storeStocks[] = [
                            'store_id' => $batch->store_id,
                            'store_name' => $batch->store_name,
                            'quantity' => $qty
                        ];
                    }
                }

                return [
                    'id' => $pr->id,
                    'product_request_id' => $pr->id,
                    'product_name' => $pr->item_name,
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
                    'adapted_from_product_name' => optional($pr->adaptedFromProduct)->product_name,
                    'adapted_from_product_code' => optional($pr->adaptedFromProduct)->product_code,
                    'adaptation_note' => $pr->adaptation_note,
                    'adapted_at' => $pr->adapted_at ? $pr->adapted_at->format('M j, Y h:i A') : null,
                    'adapted_by_name' => $pr->adapted_by ? userfullname($pr->adapted_by) : null,
                    'qty_adjusted_from' => $pr->qty_adjusted_from,
                    'qty_adjustment_reason' => $pr->qty_adjustment_reason,
                    'qty_adjusted_at' => $pr->qty_adjusted_at ? $pr->qty_adjusted_at->format('M j, Y h:i A') : null,
                    'qty_adjusted_by_name' => $pr->qty_adjusted_by ? userfullname($pr->qty_adjusted_by) : null,
                ];
            });

        // Get counts for subtabs
        $allPending = ProductRequest::where('patient_id', $patientId)
            ->where(function ($subQuery) {
                $subQuery->whereNull('product_request_id')
                    ->orWhereHas('productOrServiceRequest', function ($posr) {
                        $posr->whereNull('removed_at');
                    });
            })
            ->whereIn('status', [1, 2])
            ->count();
        $unbilledCount = ProductRequest::where('patient_id', $patientId)
            ->where(function ($subQuery) {
                $subQuery->whereNull('product_request_id')
                    ->orWhereHas('productOrServiceRequest', function ($posr) {
                        $posr->whereNull('removed_at');
                    });
            })
            ->where('status', 1)
            ->count();

        // Billed but not ready
        $billedNotReadyCount = ProductRequest::where('patient_id', $patientId)
            ->where(function ($subQuery) {
                $subQuery->whereNull('product_request_id')
                    ->orWhereHas('productOrServiceRequest', function ($posr) {
                        $posr->whereNull('removed_at');
                    });
            })
            ->where('status', 2)
            ->where(function($q) {
                $q->where(function ($own) {
                    $own->whereHas('productOrServiceRequest', function($sq) {
                        $sq->whereNull('payment_id')
                           ->where(function ($v) {
                               $v->whereNull('validation_status')
                                 ->orWhereNotIn('validation_status', ['validated', 'approved', 'awaiting_code']);
                           });
                    });
                })->where(function ($parentState) {
                    $parentState->whereDoesntHave('productOrServiceRequest.parent')
                        ->orWhereHas('productOrServiceRequest.parent', function ($parent) {
                            $parent->whereNull('payment_id')
                                ->where(function ($v) {
                                    $v->whereNull('validation_status')
                                      ->orWhereNotIn('validation_status', ['validated', 'approved', 'awaiting_code']);
                                });
                        });
                });
            })
            ->count();

        // Ready to dispense count
        $readyCount = ProductRequest::where('patient_id', $patientId)
            ->where(function ($subQuery) {
                $subQuery->whereNull('product_request_id')
                    ->orWhereHas('productOrServiceRequest', function ($posr) {
                        $posr->whereNull('removed_at');
                    });
            })
            ->where('status', 2)
            ->where(function($q) {
                $q->whereHas('productOrServiceRequest', function($sq) {
                      $sq->whereNotNull('payment_id');
                  })
                  ->orWhereHas('productOrServiceRequest', function($sq) {
                      $sq->whereIn('validation_status', ['validated', 'approved', 'awaiting_code']);
                  })
                  ->orWhereHas('productOrServiceRequest.parent', function($sq) {
                      $sq->whereNotNull('payment_id');
                  })
                  ->orWhereHas('productOrServiceRequest.parent', function($sq) {
                      $sq->whereIn('validation_status', ['validated', 'approved', 'awaiting_code']);
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
                'product_name' => $pr->item_name,
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
                'product_name' => $productRequest->item_name,
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

            // Check if this is a bundled procedure item
            $bundledCheck = HmoHelper::isBundledItem('product', $productRequest->id);
            if ($bundledCheck && $bundledCheck['is_bundled']) {
                // Use bundled item delivery check
                $deliveryCheck = HmoHelper::canDeliverBundledItem($bundledCheck['procedure_item']);
                if (!$deliveryCheck['can_deliver']) {
                    $result['valid'] = false;
                    $result['error'] = $deliveryCheck['reason'] . ' (Bundled with: ' . $bundledCheck['procedure_name'] . ')';
                    $result['error_type'] = 'bundled_procedure_block';
                    $result['bundled_info'] = [
                        'procedure_id' => $bundledCheck['procedure_id'],
                        'procedure_name' => $bundledCheck['procedure_name'],
                    ];
                    $allValid = false;
                    $totalIssues++;
                    $validationResults[] = $result;
                    continue;
                }
            } elseif ($productRequest->productOrServiceRequest) {
                // Check HMO delivery requirements for non-bundled items
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
                $result['error'] = "Insufficient stock in {$store->store_name}: need {$qty}, have {$storeQty}";
                $result['error_type'] = 'insufficient_stock';
                $result['shortage'] = $qty - $storeQty;
                $allValid = false;
                $totalIssues++;
            }

            // ── Plan §7.5.1 Readiness Chip ───────────────────────────────────────────
            // Four states: ready | billing_pending | hmo_blocked | stock_short
            $result['readiness_chip'] = match (true) {
                $result['error_type'] === 'not_billed'               => 'billing_pending',
                in_array($result['error_type'], ['hmo_block', 'bundled_procedure_block']) => 'hmo_blocked',
                $result['error_type'] === 'insufficient_stock'       => 'stock_short',
                $result['valid']                                     => 'ready',
                default                                              => 'blocked',
            };
            // ─────────────────────────────────────────────────────────────────────

            $validationResults[] = $result;
        }

        // ── Plan §7.5.1 — Store Governance block check ─────────────────────────────
        // If the user cannot dispense from this store, all chips become 'governance_blocked'.
        $storeGovernanceBlocked = false;
        $storeGovernanceMessage = null;
        if ($store) {
            $gateCheck = Gate::inspect('dispense-from-store', $store);
            if ($gateCheck->denied()) {
                $storeGovernanceBlocked = true;
                $storeGovernanceMessage = $gateCheck->message();
                foreach ($validationResults as &$r) {
                    $r['readiness_chip'] = 'governance_blocked';
                }
                unset($r);
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        return response()->json([
            'success' => true,
            'all_valid' => $allValid && ! $storeGovernanceBlocked,
            'total_items' => count($request->product_request_ids),
            'total_issues' => $totalIssues,
            'store_id' => $storeId,
            'store_name' => $store->store_name ?? '',
            'store_governance_blocked' => $storeGovernanceBlocked,
            'store_governance_message' => $storeGovernanceMessage,
            'validation_results' => $validationResults
        ]);
    }

    /**
     * Dispense free-form medication (bypass billing and stock)
     */
    public function dispenseFreeFormMedication(Request $request)
    {
        $request->validate([
            'request_id' => 'required|exists:product_requests,id',
            'qty_dispensed' => 'required|numeric|min:1',
        ]);

        $item = ProductRequest::findOrFail($request->request_id);

        if (!$item->is_free_form) {
            return response()->json([
                'success' => false,
                'message' => 'Only free-form medications can be dispensed via this route.'
            ], 403);
        }

        $item->status = 3; // Dispensed
        $item->qty = $request->qty_dispensed;
        $item->dispensed_by = Auth::id();
        $item->dispense_date = now();
        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Medication marked as dispensed.'
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

        // ── Store Governance Gate (Plan §6.2, §7.5, §B6) ─────────────────────────
        // Validates that the dispensing user has the 'dispense-from-store' permission
        // AND that the store allows_direct_patient_dispense (StoreGovernancePolicy checks both).
        // Does NOT modify StockService.
        $gateCheck = Gate::inspect('dispense-from-store', $store);
        if ($gateCheck->denied()) {
            return response()->json([
                'success' => false,
                'message' => $gateCheck->message(),
                'gate_error' => true,
            ], 403);
        }
        // ─────────────────────────────────────────────────────────────────────

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

            // Check if this is a bundled procedure item
            $bundledCheck = HmoHelper::isBundledItem('product', $productRequest->id);
            if ($bundledCheck && $bundledCheck['is_bundled']) {
                // Use bundled item delivery check
                $deliveryCheck = HmoHelper::canDeliverBundledItem($bundledCheck['procedure_item']);
                if (!$deliveryCheck['can_deliver']) {
                    $validationErrors[] = [
                        'id' => $prId,
                        'product' => $productRequest->product->name ?? 'Unknown',
                        'error' => $deliveryCheck['reason'] . ' (Bundled with: ' . $bundledCheck['procedure_name'] . ')'
                    ];
                    continue;
                }
            } elseif ($productRequest->productOrServiceRequest) {
                // Check HMO delivery requirements for non-bundled items
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
        // Uses StockService for batch-aware FIFO dispensing when batches exist,
        // which auto-syncs store_stocks + global stocks via StockBatchObserver.
        // Falls back to direct store_stock deduction for unbatched legacy stock.
        try {
            DB::beginTransaction();

            $stockService = app(StockService::class);
            $dispensedCount = 0;

            foreach ($itemsToDispense as $item) {
                $productRequest = $item['productRequest'];
                $storeStock = $item['storeStock'];
                $qty = $item['qty'];

                // Check if batches exist for this product+store
                $hasBatches = StockBatch::where('product_id', $productRequest->product_id)
                    ->where('store_id', $storeId)
                    ->where('is_active', true)
                    ->where('current_qty', '>', 0)
                    ->exists();

                if ($hasBatches) {
                    // Batch-aware FIFO dispensing
                    // dispenseStock() deducts from batches (FIFO), records transactions,
                    // triggers StockBatchObserver → syncStoreStock() → auto-syncs all tables
                    $stockService->dispenseStock(
                        $productRequest->product_id,
                        $storeId,
                        $qty,
                        ProductRequest::class,
                        $productRequest->id,
                        "Dispensing: PR#{$productRequest->id}"
                    );
                } else {
                    // Legacy fallback: no batches, deduct directly from store_stock + global
                    $storeStock->decrement('current_quantity', $qty);
                    $storeStock->increment('quantity_sale', $qty);

                    $globalStock = $productRequest->product->stock;
                    if ($globalStock) {
                        $globalStock->decrement('current_quantity', $qty);
                        $globalStock->increment('quantity_sale', $qty);
                    }
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
                'product_name' => $pr->item_name,
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

        $products = Product::with(['category', 'price', 'stock', 'packagings'])
            ->drugsOnly()
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

                // Get top 5 stores with stock for this product (filtered by Hub & Satellite stores)
                $storeStocks = \App\Models\StockBatch::where('product_id', $product->id)
                    ->where('current_qty', '>', 0)
                    ->whereHas('store', function ($q) {
                        $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                    })
                    ->selectRaw('store_id, SUM(current_qty) as total_qty')
                    ->groupBy('store_id')
                    ->orderByDesc('total_qty')
                    ->limit(5)
                    ->get()
                    ->map(function($batch) {
                        $store = \App\Models\Store::find($batch->store_id);
                        return [
                            'store_id' => $batch->store_id,
                            'store_name' => $store ? $store->store_name : 'Unknown Store',
                            'quantity' => (int) $batch->total_qty
                        ];
                    });

                // Calculate global stock from pharmacy stores
                $globalStock = $storeStocks->sum('quantity');

                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'product_code' => $product->product_code,
                    'product_type' => $product->product_type ?? 'drug',
                    'base_unit_name' => $product->base_unit_name ?? 'Piece',
                    'allow_decimal_qty' => $product->allow_decimal_qty ?? false,
                    'category_name' => optional($product->category)->category_name,
                    'price' => $basePrice,
                    'stock_qty' => $globalStock,
                    'stock_formatted' => $product->formatQty($globalStock),
                    'store_stocks' => $storeStocks->toArray(),
                    'payable_amount' => $payableAmount,
                    'claims_amount' => $claimsAmount,
                    'coverage_mode' => $coverageMode,
                    'packagings' => $product->packagings->sortBy('level')->map(function($pkg) {
                        return [
                            'id' => $pkg->id,
                            'name' => $pkg->name,
                            'level' => $pkg->level,
                            'units_in_parent' => (float) $pkg->units_in_parent,
                            'base_unit_qty' => (float) $pkg->base_unit_qty,
                            'is_default_purchase' => (bool) $pkg->is_default_purchase,
                            'is_default_dispense' => (bool) $pkg->is_default_dispense,
                        ];
                    })->values(),
                    'is_combo' => false,
                    'bundle_items' => [],
                ];
            });

        // --- Combo detection ---
        // Find combos that contain products matching the search term, or whose name matches
        $matchingProductIds = Product::drugsOnly()
            ->where(function ($q) use ($term) {
                $q->where('product_name', 'like', "%{$term}%")
                  ->orWhere('product_code', 'like', "%{$term}%");
            })
            ->where('status', 1)
            ->pluck('id');

        $comboServiceIds = collect();
        if ($matchingProductIds->isNotEmpty()) {
            $comboServiceIds = ServiceBundleItem::whereIn('item_id', $matchingProductIds)
                ->where('item_type', 'product')
                ->pluck('parent_service_id')
                ->unique();
        }

        $comboByNameIds = Service::where('is_combo', true)
            ->where(function ($q) use ($term) {
                $q->where('service_name', 'like', "%{$term}%")
                  ->orWhere('service_code', 'like', "%{$term}%");
            })
            ->pluck('id');

        $allComboIds = $comboServiceIds->merge($comboByNameIds)->unique();

        $comboResults = collect();
        if ($allComboIds->isNotEmpty()) {
            $combos = Service::with([
                'bundleItems.product.category',
                'bundleItems.product.price',
                'bundleItems.product.stock',
                'bundleItems.product.packagings',
                'bundleItems.service',
                'price'
            ])
                ->where('is_combo', true)
                ->whereIn('id', $allComboIds)
                ->get();

            // Batch-load HMO tariffs for combos
            $comboTariffMap = [];
            if ($patient && $patient->hmo_id) {
                $svcIds = $combos->pluck('id')->toArray();
                $previews = HmoHelper::batchPreviewTariffs($patient->hmo_id, [], $svcIds);
                $comboTariffMap = $previews['services'] ?? [];
            }

            $comboResults = $combos->map(function ($combo) use ($patient, $comboTariffMap) {
                $basePrice = optional($combo->price)->current_sale_price
                    ?? optional($combo->price)->sale_price ?? 0;
                $payableAmount = $basePrice;
                $claimsAmount  = 0;
                $coverageMode  = null;

                if ($patient && $patient->hmo_id) {
                    $t = $comboTariffMap[$combo->id] ?? null;
                    if ($t && isset($t['payable_amount'])) {
                        $payableAmount = $t['payable_amount'];
                        $claimsAmount  = $t['claims_amount'] ?? 0;
                        $coverageMode  = $t['coverage_mode'] ?? null;
                    }
                }

                $bundleItems = $combo->bundleItems->map(function ($item) use ($patient) {
                    if ($item->item_type === 'product') {
                        $prod = $item->product;
                        $itemBasePrice = optional($prod->price)->current_sale_price ?? 0;
                        $itemPayableAmount = $itemBasePrice;
                        $itemClaimsAmount = 0;
                        $itemCoverageMode = null;

                        if ($patient && $patient->hmo_id && $prod) {
                            try {
                                $tariffInfo = HmoHelper::applyHmoTariff($patient->id, $prod->id);
                                if ($tariffInfo) {
                                    $itemPayableAmount = $tariffInfo['payable_amount'] ?? $itemBasePrice;
                                    $itemClaimsAmount = $tariffInfo['claims_amount'] ?? 0;
                                    $itemCoverageMode = $tariffInfo['coverage_mode'] ?? null;
                                }
                            } catch (\Exception $e) {
                                // No tariff found, use base price
                            }
                        }

                        $itemStockQty = optional($prod->stock)->current_quantity ?? 0;

                        return [
                            'id' => $item->item_id,
                            'name' => $prod ? $prod->product_name : '(unknown)',
                            'code' => $prod ? $prod->product_code : null,
                            'qty' => (float) $item->qty,
                            'type' => 'product',
                            'product_type' => $prod->product_type ?? 'drug',
                            'base_unit_name' => $prod->base_unit_name ?? 'Piece',
                            'allow_decimal_qty' => $prod->allow_decimal_qty ?? false,
                            'category_name' => optional($prod->category)->category_name,
                            'price' => $itemBasePrice,
                            'stock_qty' => $itemStockQty,
                            'payable_amount' => $itemPayableAmount,
                            'claims_amount' => $itemClaimsAmount,
                            'coverage_mode' => $itemCoverageMode,
                            'packagings' => $prod ? $prod->packagings->sortBy('level')->map(function ($pkg) {
                                return [
                                    'id' => $pkg->id,
                                    'name' => $pkg->name,
                                    'level' => $pkg->level,
                                    'units_in_parent' => (float) $pkg->units_in_parent,
                                    'base_unit_qty' => (float) $pkg->base_unit_qty,
                                    'is_default_purchase' => (bool) $pkg->is_default_purchase,
                                    'is_default_dispense' => (bool) $pkg->is_default_dispense,
                                ];
                            })->values()->toArray() : [],
                        ];
                    }
                    $svc = $item->service;
                    return [
                        'id' => $item->item_id,
                        'name' => $svc ? $svc->service_name : '(unknown)',
                        'qty' => (float) $item->qty,
                        'type' => 'service',
                    ];
                })->values()->toArray();

                return [
                    'id'                 => $combo->id,
                    'product_name'       => $combo->service_name,
                    'product_code'       => $combo->service_code ?? null,
                    'product_type'       => 'combo',
                    'base_unit_name'     => 'Package',
                    'allow_decimal_qty'  => false,
                    'category_name'      => 'Combo',
                    'price'              => $basePrice,
                    'stock_qty'          => 99,
                    'stock_formatted'    => 'N/A',
                    'store_stocks'       => [],
                    'payable_amount'     => $payableAmount,
                    'claims_amount'      => $claimsAmount,
                    'coverage_mode'      => $coverageMode,
                    'packagings'         => [],
                    'is_combo'           => true,
                    'bundle_items'       => $bundleItems,
                ];
            });
        }

        return response()->json($products->merge($comboResults)->values());
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
            'products.*.product_id' => 'required|string',
            'products.*.qty' => 'required|integer|min:1',
            'products.*.dose' => 'nullable|string',
        ]);

        $patient = Patient::findOrFail($request->patient_id);

        // Validate regular products exist
        $regularProductIds = array_filter(array_column($request->products, 'product_id'), function($id) {
            return strpos($id, 'FF_') !== 0;
        });
        if (count($regularProductIds) > 0) {
            $existingCount = \App\Models\Product::whereIn('id', $regularProductIds)->count();
            if ($existingCount !== count($regularProductIds)) {
                return response()->json(['success' => false, 'message' => 'One or more selected products are invalid.'], 422);
            }
        }

        $patient = Patient::findOrFail($request->patient_id);

        DB::beginTransaction();
        try {
            $createdRequests = [];

            foreach ($request->products as $productData) {
                $productId = $productData['product_id'];
                $isFreeForm = strpos($productId, 'FF_') === 0;
                $dbProductId = null;
                $freeFormName = null;

                if ($isFreeForm) {
                    $freeFormName = str_replace(' [Free-form]', '', substr($productId, 3));
                } else {
                    $product = Product::with('price')->findOrFail($productId);
                    $dbProductId = $product->id;
                }

                // Create ProductRequest ONLY (status=1 means unbilled/requested)
                // This matches EncounterController::savePrescriptions() behavior
                $productRequest = ProductRequest::create([
                    'patient_id' => $patient->id,
                    'product_id' => $dbProductId,
                    'is_free_form' => $isFreeForm,
                    'free_form_name' => $freeFormName,
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

                    // Reuse existing ProductOrServiceRequest created at reception when available.
                    // Only create a new billing record if none exists.
                    $billReq = null;
                    if (!empty($productRequest->product_request_id)) {
                        $billReq = ProductOrServiceRequest::find($productRequest->product_request_id);
                    }

                    if (!$billReq) {
                        $billReq = new ProductOrServiceRequest();
                        $billReq->user_id = $request->patient_user_id;
                        $billReq->staff_user_id = Auth::id();
                        $billReq->product_id = $prodId;
                        $billReq->qty = $productRequest->qty; // Use the qty from ProductRequest (may have been adjusted)

                        \Log::info('PharmacyWorkbench: Before applyHmoTariff', [
                            'product_request_id' => $productRequest->id,
                            'product_id' => $prodId,
                            'qty_from_product_request' => $productRequest->qty,
                            'billReq_qty_before' => $billReq->qty,
                            'price_override' => $productRequest->price_override,
                        ]);

                        // Apply HMO tariff if patient has HMO (respects price_override)
                        $this->applyHmoTariffToRequest($billReq, $patient, $prodId, $productRequest->product, $productRequest->qty, $productRequest->price_override);

                        \Log::info('PharmacyWorkbench: After applyHmoTariff', [
                            'product_request_id' => $productRequest->id,
                            'billReq_qty_after' => $billReq->qty,
                            'payable_amount' => $billReq->payable_amount,
                            'claims_amount' => $billReq->claims_amount
                        ]);

                        $billReq->save();

                        \Log::info('PharmacyWorkbench: After save', [
                            'product_request_id' => $productRequest->id,
                            'bill_request_id' => $billReq->id,
                            'saved_qty' => $billReq->qty
                        ]);
                    }

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
                    $billReq->qty = 1; // Default qty for newly added items

                    // Apply HMO tariff
                    $this->applyHmoTariffToRequest($billReq, $patient, $productId, $product, 1);

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
    private function applyHmoTariffToRequest(ProductOrServiceRequest $billReq, Patient $patient, $productId, $product = null, $qty = 1, $priceOverride = null)
    {
        // If a pre-billing price override exists, use it instead of tariff/sale price
        if ($priceOverride !== null) {
            $overrideTotal = round($priceOverride * $qty, 2);

            if ($patient->hmo_id) {
                // For HMO patients: look up tariff to determine total, then HMO covers the difference
                try {
                    $hmoData = HmoHelper::applyHmoTariff($patient->id, $productId, null);
                    if ($hmoData) {
                        $tariffTotal = round(($hmoData['payable_amount'] + $hmoData['claims_amount']) * $qty, 2);
                        $billReq->payable_amount = $overrideTotal;
                        $billReq->claims_amount = max(0, $tariffTotal - $overrideTotal);
                        $billReq->coverage_mode = $hmoData['coverage_mode'];
                        $billReq->validation_status = $hmoData['validation_status'] ?? 'pending';

                        Log::info('Price override applied with HMO tariff', [
                            'product_id' => $productId,
                            'price_override' => $priceOverride,
                            'qty' => $qty,
                            'override_total' => $overrideTotal,
                            'tariff_total' => $tariffTotal,
                            'final_payable' => $billReq->payable_amount,
                            'final_claims' => $billReq->claims_amount,
                        ]);
                        return;
                    }
                } catch (\Exception $e) {
                    Log::warning('HMO tariff lookup failed during price override', ['error' => $e->getMessage()]);
                }
            }

            // Cash patient or HMO tariff failed: full amount from override
            $billReq->payable_amount = $overrideTotal;
            $billReq->claims_amount = 0;
            $billReq->coverage_mode = 'none';

            Log::info('Price override applied (cash)', [
                'product_id' => $productId,
                'price_override' => $priceOverride,
                'qty' => $qty,
                'final_payable' => $overrideTotal,
            ]);
            return;
        }

        try {
            if ($patient->hmo_id) {
                $hmoData = HmoHelper::applyHmoTariff($patient->id, $productId, null);
                if ($hmoData) {
                    // Multiply by quantity
                    $billReq->payable_amount = $hmoData['payable_amount'] * $qty;
                    $billReq->claims_amount = $hmoData['claims_amount'] * $qty;
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
        $billReq->payable_amount = $price * $qty; // Multiply by quantity
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
            ->where('status', 1)
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

    // ===== BATCH-BASED INVENTORY METHODS =====

    /**
     * Get available batches for a product in a store
     * Used by the dispense modal to show batch selection options
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductBatches(Request $request)
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
     * Get batch fulfillment suggestion for dispensing
     * Returns optimal FIFO batch allocation for required quantity
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBatchFulfillmentSuggestion(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'store_id' => 'required|exists:stores,id',
            'qty' => 'required|integer|min:1',
        ]);

        $suggestion = BatchHelper::suggestFulfillmentStrategy(
            $request->product_id,
            $request->store_id,
            $request->qty
        );

        return response()->json([
            'success' => true,
            'suggestion' => $suggestion,
        ]);
    }

    /**
     * Dispense medication with batch selection
     * Enhanced version with FIFO batch-based stock deduction
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dispenseMedicationWithBatch(Request $request)
    {
        $request->validate([
            'product_request_ids' => 'required|array',
            'product_request_ids.*' => 'exists:product_requests,id',
            'store_id' => 'required|exists:stores,id',
            'batch_selections' => 'nullable|array', // Optional: manual batch selection
            'batch_selections.*.product_request_id' => 'exists:product_requests,id',
            'batch_selections.*.batch_id' => 'exists:stock_batches,id',
        ]);

        $storeId = $request->store_id;
        $store = Store::find($storeId);
        $stockService = app(StockService::class);

        // Build batch selections map
        $manualBatchSelections = [];
        if ($request->has('batch_selections')) {
            foreach ($request->batch_selections as $selection) {
                $manualBatchSelections[$selection['product_request_id']] = $selection['batch_id'];
            }
        }

        // PHASE 1: Pre-validate ALL items
        $itemsToDispense = [];
        $validationErrors = [];

        foreach ($request->product_request_ids as $prId) {
            $productRequest = ProductRequest::with(['productOrServiceRequest', 'product'])->find($prId);

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
                    'product' => $productRequest->item_name,
                    'error' => "Cannot dispense - item is '{$statusLabel}' (must be 'Billed')"
                ];
                continue;
            }

            // Check if this is a bundled procedure item
            $bundledCheck = HmoHelper::isBundledItem('product', $productRequest->id);
            if ($bundledCheck && $bundledCheck['is_bundled']) {
                $deliveryCheck = HmoHelper::canDeliverBundledItem($bundledCheck['procedure_item']);
                if (!$deliveryCheck['can_deliver']) {
                    $validationErrors[] = [
                        'id' => $prId,
                        'product' => $productRequest->item_name,
                        'error' => $deliveryCheck['reason'] . ' (Bundled with: ' . $bundledCheck['procedure_name'] . ')'
                    ];
                    continue;
                }
            } elseif ($productRequest->productOrServiceRequest) {
                $deliveryCheck = HmoHelper::canDeliverService($productRequest->productOrServiceRequest);
                if (!$deliveryCheck['can_deliver']) {
                    $validationErrors[] = [
                        'id' => $prId,
                        'product' => $productRequest->item_name,
                        'error' => $deliveryCheck['reason']
                    ];
                    continue;
                }
            }

            $qty = $productRequest->qty ?? 1;

            // Check batch availability
            $availableQty = $stockService->getAvailableStock($productRequest->product_id, $storeId);

            if ($availableQty < $qty) {
                $validationErrors[] = [
                    'id' => $prId,
                    'product' => $productRequest->item_name,
                    'error' => "Insufficient stock in '{$store->store_name}': need {$qty}, available {$availableQty}",
                    'shortage' => $qty - $availableQty
                ];
                continue;
            }

            // Determine batch to use
            $batchId = $manualBatchSelections[$prId] ?? null;

            // Validate manual batch selection if provided
            if ($batchId) {
                $batch = StockBatch::find($batchId);
                if (!$batch || $batch->product_id != $productRequest->product_id || $batch->store_id != $storeId) {
                    $validationErrors[] = [
                        'id' => $prId,
                        'product' => $productRequest->item_name,
                        'error' => 'Invalid batch selection'
                    ];
                    continue;
                }
                if ($batch->current_qty < $qty) {
                    $validationErrors[] = [
                        'id' => $prId,
                        'product' => $productRequest->item_name,
                        'error' => "Selected batch has insufficient stock: need {$qty}, available {$batch->current_qty}"
                    ];
                    continue;
                }
            }

            $itemsToDispense[] = [
                'productRequest' => $productRequest,
                'qty' => $qty,
                'batch_id' => $batchId,
            ];
        }

        // Reject if validation errors
        if (count($validationErrors) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot dispense: ' . count($validationErrors) . ' item(s) failed validation.',
                'validation_errors' => $validationErrors,
                'dispensed_count' => 0
            ], 422);
        }

        // PHASE 2: Execute dispense in transaction
        try {
            DB::beginTransaction();

            $dispensedCount = 0;

            foreach ($itemsToDispense as $item) {
                $productRequest = $item['productRequest'];
                $qty = $item['qty'];
                $batchId = $item['batch_id'];

                // Dispense using batch system
                if ($batchId) {
                    // Manual batch selection
                    $transaction = $stockService->dispenseFromBatch(
                        $batchId,
                        $qty,
                        ProductRequest::class,
                        $productRequest->id,
                        "Dispensed for patient"
                    );
                    $dispensedBatchId = $batchId;
                } else {
                    // FIFO automatic batch selection
                    $dispensed = $stockService->dispenseStock(
                        $productRequest->product_id,
                        $storeId,
                        $qty,
                        ProductRequest::class,
                        $productRequest->id,
                        "Dispensed for patient"
                    );
                    // Get the first batch used (for recording)
                    $dispensedBatchId = array_key_first($dispensed);
                }

                // Update product request
                $productRequest->update([
                    'status' => 3,
                    'dispensed_by' => Auth::id(),
                    'dispense_date' => now(),
                    'dispensed_from_store_id' => $storeId,
                    'dispensed_from_batch_id' => $dispensedBatchId,
                ]);

                // Update ProductOrServiceRequest if exists
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
                'message' => "Successfully dispensed {$dispensedCount} medication(s) from '{$store->store_name}' using batch tracking",
                'dispensed_count' => $dispensedCount,
                'store_name' => $store->store_name
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Pharmacy batch dispense error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Dispense error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expiring batches alert for pharmacy dashboard
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExpiringBatches(Request $request)
    {
        $storeId = $request->get('store_id', Store::getDefaultPharmacy()?->id);
        $days = $request->get('days', 30);

        if (!$storeId) {
            return response()->json([
                'success' => false,
                'message' => 'No store selected'
            ], 400);
        }

        $expiringBatches = BatchHelper::getBatchesWithExpiryWarning($storeId, 90, $days);

        return response()->json([
            'success' => true,
            'batches' => $expiringBatches->map(fn($b) => [
                'batch_id' => $b['batch']->id,
                'batch_number' => $b['batch']->batch_number,
                'product_name' => $b['batch']->product->product_name ?? 'Unknown',
                'current_qty' => $b['batch']->current_qty,
                'expiry_date' => $b['batch']->expiry_date?->format('Y-m-d'),
                'days_to_expiry' => $b['days_to_expiry'],
                'warning_level' => $b['warning_level'],
            ]),
            'summary' => [
                'expired' => $expiringBatches->where('is_expired', true)->count(),
                'critical' => $expiringBatches->where('is_critical', true)->count(),
                'warning' => $expiringBatches->count() - $expiringBatches->where('is_expired', true)->count() - $expiringBatches->where('is_critical', true)->count(),
            ],
        ]);
    }

    /**
     * Get low stock items alert for pharmacy dashboard
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLowStockItems(Request $request)
    {
        $storeId = $request->get('store_id', Store::getDefaultPharmacy()?->id);

        if (!$storeId) {
            return response()->json([
                'success' => false,
                'message' => 'No store selected'
            ], 400);
        }

        $stockService = app(StockService::class);
        $lowStockItems = $stockService->getLowStockProducts($storeId);

        return response()->json([
            'success' => true,
            'items' => $lowStockItems->map(fn($ss) => [
                'product_id' => $ss->product_id,
                'product_name' => $ss->product->product_name ?? 'Unknown',
                'product_code' => $ss->product->product_code ?? '-',
                'current_qty' => $ss->qty,
                'reorder_level' => $ss->reorder_level,
                'shortage' => max(0, $ss->reorder_level - $ss->qty),
            ]),
            'total_count' => $lowStockItems->count(),
        ]);
    }

    /**
     * Pre-billing price override for an unbilled prescription item.
     *
     * Allows pharmacists to override the unit price before billing so that
     * the billing step uses the adjusted price instead of the tariff / sale price.
     * Stores full audit trail including the original price.
     *
     * @param Request $request
     * @param int $id ProductRequest ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function adjustPrice(Request $request, $id)
    {
        $request->validate([
            'new_price' => 'required|numeric|min:0',
            'adjustment_reason' => 'required|string|max:500',
        ]);

        $productRequest = ProductRequest::with(['product.price', 'patient.hmo'])->findOrFail($id);

        // Only unbilled items (status=1) can have price overrides
        if ($productRequest->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Price can only be adjusted on unbilled items (before billing)'
            ], 422);
        }

        $product = $productRequest->product;
        $currentPrice = optional(optional($product)->price)->current_sale_price ?? 0;
        $newPrice = round((float) $request->new_price, 2);
        $qty = $productRequest->qty ?? 1;

        // Prevent setting same price
        $effectivePrice = $productRequest->price_override ?? $currentPrice;
        if (abs($effectivePrice - $newPrice) < 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'New price is the same as the current price'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $productRequest->update([
                'price_override' => $newPrice,
                'price_original' => $productRequest->price_original ?? $currentPrice,
                'price_override_reason' => $request->adjustment_reason,
                'price_override_by' => Auth::id(),
                'price_override_at' => now(),
            ]);

            DB::commit();

            // Detailed audit log
            Log::info('Pre-billing price override applied', [
                'product_request_id' => $id,
                'product_id' => $product->id ?? null,
                'product_name' => $product->product_name ?? 'Unknown',
                'patient_id' => $productRequest->patient_id,
                'original_unit_price' => $currentPrice,
                'previous_override' => $effectivePrice,
                'new_unit_price' => $newPrice,
                'qty' => $qty,
                'old_line_total' => $effectivePrice * $qty,
                'new_line_total' => $newPrice * $qty,
                'difference_per_unit' => round($newPrice - $effectivePrice, 2),
                'difference_total' => round(($newPrice - $effectivePrice) * $qty, 2),
                'reason' => $request->adjustment_reason,
                'adjusted_by' => Auth::id(),
                'adjusted_by_name' => Auth::user()->name ?? '',
            ]);

            $direction = $newPrice > $effectivePrice ? 'increased' : 'reduced';

            return response()->json([
                'success' => true,
                'message' => "Unit price {$direction} from ₦" . number_format($effectivePrice, 2) . " to ₦" . number_format($newPrice, 2),
                'original_price' => $currentPrice,
                'new_price' => $newPrice,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Price override failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust price: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adapt/change a prescription to a different product
     * Records the original product for audit trail
     *
     * @param Request $request
     * @param int $id ProductRequest ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function adaptPrescription(Request $request, $id)
    {
        $request->validate([
            'new_product_id' => 'required|exists:products,id',
            'new_qty' => 'required|integer|min:1',
            'adaptation_note' => 'required|string|max:500',
        ]);

        $productRequest = ProductRequest::with(['productOrServiceRequest.payment'])->findOrFail($id);

        // Allow adaptation for pending (status < 2) and billed (status == 2) items
        // Do NOT allow for dispensed items (status >= 3)
        if ($productRequest->status >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot adapt prescription that has already been dispensed'
            ], 422);
        }

        // For billed items, check settlement status
        if ($productRequest->status == 2 && $productRequest->productOrServiceRequest) {
            $posr = $productRequest->productOrServiceRequest;
            $isPaid = $posr->payment_id !== null;
            $isValidated = in_array($posr->validation_status, ['validated', 'approved']);
            $hasPayable = ($posr->payable_amount ?? 0) > 0;
            $hasClaims = ($posr->claims_amount ?? 0) > 0;

            // Block if any settlement has occurred
            if ($hasPayable && !$hasClaims && $isPaid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot adapt prescription - payment has already been received'
                ], 422);
            }
            if (!$hasPayable && $hasClaims && $isValidated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot adapt prescription - HMO claim has already been validated'
                ], 422);
            }
            if ($hasPayable && $hasClaims && ($isPaid || $isValidated)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot adapt prescription - billing has been partially or fully settled'
                ], 422);
            }
        }

        $originalProductId = $productRequest->product_id;
        $originalQty = $productRequest->qty;
        $newProduct = Product::findOrFail($request->new_product_id);
        $isBilled = $productRequest->status == 2;

        // Get price for new product
        $newPrice = $newProduct->price->current_sale_price ?? 0;

        try {
            DB::beginTransaction();

            // Update product request with new product
            $productRequest->update([
                'adapted_from_product_id' => $originalProductId,
                'product_id' => $request->new_product_id,
                'qty' => $request->new_qty,
                'adaptation_note' => $request->adaptation_note,
                'adapted_at' => now(),
                'adapted_by' => Auth::id(),
                // Clear any pre-billing price override — new product has its own price
                'price_override' => null,
                'price_original' => null,
                'price_override_reason' => null,
                'price_override_by' => null,
                'price_override_at' => null,
            ]);

            // Update the ProductOrServiceRequest if exists
            if ($productRequest->productOrServiceRequest) {
                $posr = $productRequest->productOrServiceRequest;
                $oldPayableAmount = $posr->payable_amount;
                $newPayableAmount = $newPrice * $request->new_qty;

                $posr->update([
                    'product_id' => $request->new_product_id,
                    'qty' => $request->new_qty,
                    'payable_amount' => $newPayableAmount,
                ]);

                // If already billed and payment record exists, create adjustment record
                if ($isBilled && $posr->payment) {
                    $priceDifference = $newPayableAmount - $oldPayableAmount;

                    // Log billing adjustment for reconciliation
                    Log::info('Billing adjustment for adapted prescription', [
                        'product_request_id' => $id,
                        'posr_id' => $posr->id,
                        'payment_id' => $posr->payment->id,
                        'old_amount' => $oldPayableAmount,
                        'new_amount' => $newPayableAmount,
                        'difference' => $priceDifference,
                        'requires_refund' => $priceDifference < 0,
                        'requires_additional_payment' => $priceDifference > 0,
                    ]);
                }
            }

            DB::commit();

            Log::info('Prescription adapted', [
                'product_request_id' => $id,
                'original_product_id' => $originalProductId,
                'original_qty' => $originalQty,
                'new_product_id' => $request->new_product_id,
                'new_qty' => $request->new_qty,
                'was_billed' => $isBilled,
                'adapted_by' => Auth::id(),
                'reason' => $request->adaptation_note,
            ]);

            $message = 'Prescription adapted successfully to ' . $newProduct->product_name;
            if ($isBilled) {
                $message .= '. Note: Billing record has been updated. Please verify payment adjustments if needed.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'was_billed' => $isBilled,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Prescription adaptation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to adapt prescription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adjust quantity for a billed prescription item
     *
     * Allows pharmacists to adjust quantity at billing stage when:
     * - Patient requests different quantity
     * - Stock limitations require adjustment
     * - Clinical review suggests dose change
     *
     * @param Request $request
     * @param int $id ProductRequest ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function adjustBilledQuantity(Request $request, $id)
    {
        $request->validate([
            'new_qty' => 'required|integer|min:1',
            'adjustment_reason' => 'required|string|max:500',
        ]);

        $productRequest = ProductRequest::with(['product.price', 'productOrServiceRequest.payment', 'patient.hmo'])
            ->findOrFail($id);

        // Allow adjustment for unbilled (status 1) and billed (status 2) items
        // Do NOT allow for dispensed items (status >= 3)
        if ($productRequest->status >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot adjust quantity for items that have already been dispensed'
            ], 422);
        }

        // For billed items with payment, check settlement status
        if ($productRequest->status == 2 && $productRequest->productOrServiceRequest) {
            $posr = $productRequest->productOrServiceRequest;
            $isPaid = $posr->payment_id !== null;
            $isValidated = in_array($posr->validation_status, ['validated', 'approved']);
            $hasPayable = ($posr->payable_amount ?? 0) > 0;
            $hasClaims = ($posr->claims_amount ?? 0) > 0;

            // Block if any settlement has occurred
            // Conditions: payable only + paid, or claims only + validated, or both + any settled
            if ($hasPayable && !$hasClaims && $isPaid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot adjust quantity - payment has already been received'
                ], 422);
            }
            if (!$hasPayable && $hasClaims && $isValidated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot adjust quantity - HMO claim has already been validated'
                ], 422);
            }
            if ($hasPayable && $hasClaims && ($isPaid || $isValidated)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot adjust quantity - billing has been partially or fully settled'
                ], 422);
            }
        }

        $originalQty = $productRequest->qty;
        $newQty = $request->new_qty;

        if ($originalQty == $newQty) {
            return response()->json([
                'success' => false,
                'message' => 'New quantity is the same as current quantity'
            ], 422);
        }

        $product = $productRequest->product;
        $unitPrice = $product->price->current_sale_price ?? 0;

        try {
            DB::beginTransaction();

            // Update product request quantity
            $productRequest->update([
                'qty' => $newQty,
                'qty_adjusted_from' => $originalQty,
                'qty_adjustment_reason' => $request->adjustment_reason,
                'qty_adjusted_at' => now(),
                'qty_adjusted_by' => Auth::id(),
            ]);

            // Update the billing record if it exists
            if ($productRequest->productOrServiceRequest) {
                $posr = $productRequest->productOrServiceRequest;
                $oldTotal = $posr->payable_amount + $posr->claims_amount;
                $oldPayableAmount = $posr->payable_amount;
                $oldClaimsAmount = $posr->claims_amount;

                // Calculate new total amount
                $newTotal = $unitPrice * $newQty;

                // Recalculate payable and claims amounts based on coverage
                $newPayableAmount = $newTotal;
                $newClaimsAmount = 0;

                // If there was HMO coverage, maintain the same coverage ratio
                if ($oldTotal > 0 && $oldClaimsAmount > 0) {
                    $payableRatio = $oldPayableAmount / $oldTotal;
                    $claimsRatio = $oldClaimsAmount / $oldTotal;

                    $newPayableAmount = $newTotal * $payableRatio;
                    $newClaimsAmount = $newTotal * $claimsRatio;
                }

                $posr->update([
                    'qty' => $newQty,
                    'payable_amount' => $newPayableAmount,
                    'claims_amount' => $newClaimsAmount,
                ]);

                // Log billing adjustment
                $priceDifference = ($newPayableAmount + $newClaimsAmount) - ($oldPayableAmount + $oldClaimsAmount);

                Log::info('Billing quantity adjustment', [
                    'product_request_id' => $id,
                    'posr_id' => $posr->id,
                    'product_id' => $product->id,
                    'product_name' => $product->product_name,
                    'original_qty' => $originalQty,
                    'new_qty' => $newQty,
                    'unit_price' => $unitPrice,
                    'old_payable_amount' => $oldPayableAmount,
                    'new_payable_amount' => $newPayableAmount,
                    'old_claims_amount' => $oldClaimsAmount,
                    'new_claims_amount' => $newClaimsAmount,
                    'total_difference' => $priceDifference,
                    'adjusted_by' => Auth::id(),
                    'reason' => $request->adjustment_reason,
                ]);
            }

            DB::commit();

            $qtyChange = $newQty > $originalQty ? 'increased' : 'reduced';

            return response()->json([
                'success' => true,
                'message' => "Quantity {$qtyChange} from {$originalQty} to {$newQty}. Billing record updated.",
                'original_qty' => $originalQty,
                'new_qty' => $newQty,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Quantity adjustment failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust quantity: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get executive summary for pharmacy reports (JSON response)
     */
    public function getExecutiveSummary(Request $request)
    {
        $data = $this->fetchExecutiveSummaryData($request);
        return response()->json($data);
    }

    /**
     * Print detailed executive summary
     */
    public function printExecutiveSummary(Request $request)
    {
        $data = $this->fetchExecutiveSummaryData($request);
        $data['appsettings'] = appsettings();
        $data['pharmacist'] = userfullname(Auth::id());
        $data['print_date'] = Carbon::now()->format('d M Y H:i');
        
        $data['filters'] = [
            'date_from' => $request->date_from ? Carbon::parse($request->date_from)->format('d M Y') : 'Beginning',
            'date_to' => $request->date_to ? Carbon::parse($request->date_to)->format('d M Y') : 'Today',
            'store' => $request->store_id ? (\App\Models\Store::find($request->store_id)->store_name ?? 'All Hubs & Satellites') : 'All Hubs & Satellites'
        ];

        return view('admin.pharmacy.executive_summary_print', $data);
    }

    /**
     * Core logic for fetching executive summary data
     */
    private function fetchExecutiveSummaryData(Request $request)
    {
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : null;
        $dateTo = $request->date_to ? Carbon::parse($request->date_to)->endOfDay() : null;
        $storeId = $request->store_id;
        $ageBracketsInput = $request->age_brackets;

        $ageBrackets = [];
        if ($ageBracketsInput) {
            $parts = explode(',', $ageBracketsInput);
            foreach ($parts as $part) {
                $range = explode('-', $part);
                if (count($range) == 2) {
                    $ageBrackets[] = ['min' => (int)$range[0], 'max' => (int)$range[1], 'label' => trim($part)];
                }
            }
        }
        if (empty($ageBrackets)) {
            $ageBrackets = [
                ['min' => 0, 'max' => 12, 'label' => '0-12'],
                ['min' => 13, 'max' => 19, 'label' => '13-19'],
                ['min' => 20, 'max' => 35, 'label' => '20-35'],
                ['min' => 36, 'max' => 50, 'label' => '36-50'],
                ['min' => 51, 'max' => 65, 'label' => '51-65'],
                ['min' => 66, 'max' => 150, 'label' => '65+'],
            ];
        }

        // 1. Stock Valuation
        $stockQuery = \App\Models\StockBatch::with('product.price')
            ->where('current_qty', '>', 0)
            ->whereHas('store', function($q) {
                $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
            });
        
        if ($storeId) {
            $stockQuery->where('store_id', $storeId);
        }

        $totalStockValue = 0;
        $stockQuery->chunk(500, function ($batches) use (&$totalStockValue) {
            foreach ($batches as $batch) {
                $cost = $batch->cost_price;
                if (empty($cost) || $cost <= 0) {
                    $price = $batch->product->price ?? null;
                    $cost = $price ? $price->pr_buy_price : 0;
                }
                $totalStockValue += ($batch->current_qty * $cost);
            }
        });

        // Base query for dispensed items
        $posrQuery = \App\Models\ProductOrServiceRequest::whereNotNull('dispensed_from_store_id')
            ->whereHas('dispensedFromStore', function($q) {
                $q->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
            });

        if ($dateFrom) $posrQuery->where('created_at', '>=', $dateFrom);
        if ($dateTo) $posrQuery->where('created_at', '<=', $dateTo);
        if ($storeId) $posrQuery->where('dispensed_from_store_id', $storeId);

        // 1.5 Expenditure (Purchases)
        $reqQuery = \App\Models\StoreRequisitionItem::with(['sourceBatch', 'product.price'])
            ->where('status', 'fulfilled')
            ->whereHas('requisition', function($q) use ($dateFrom, $dateTo, $storeId) {
                $q->where('status', 'fulfilled');
                if ($dateFrom) $q->where('fulfilled_at', '>=', $dateFrom);
                if ($dateTo) $q->where('fulfilled_at', '<=', $dateTo);
                $q->whereHas('toStore', function($q2) {
                    $q2->whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE]);
                });
                if ($storeId) $q->where('to_store_id', $storeId);
            });
            
        $totalExpenditure = 0;
        $reqQuery->chunk(500, function ($requisitions) use (&$totalExpenditure) {
            foreach ($requisitions as $reqItem) {
                $cost = 0;
                if ($reqItem->sourceBatch) {
                    $batch = $reqItem->sourceBatch;
                    if ($batch && $batch->cost_price > 0) {
                        $cost = $batch->cost_price;
                    }
                }
                if ($cost <= 0) {
                    $price = $reqItem->product->price ?? null;
                    $cost = $price ? $price->pr_buy_price : 0;
                }
                $totalExpenditure += ($reqItem->fulfilled_qty * $cost);
            }
        });

        // 2. Collections by Store & Demographics Data Preparation
        $collectionsByStore = [];
        $incomeByScheme = [];
        $totalGoodsUsed = 0;
        
        $visitTypeCounts = ['Admitted' => 0, 'Out-Patient' => 0, 'Walk-in' => 0];
        $patientClassBreakdown = [];
        $genderBreakdown = [];
        $ageBreakdown = [];

        $addHmoData = function(&$breakdown, $key, $record) {
            if (!isset($breakdown[$key])) {
                $breakdown[$key] = ['count' => 0, 'schemes' => []];
            }
            $breakdown[$key]['count']++;

            $patient = $record->patient;
            $hmoId = $record->hmo_id ?: ($patient->hmo_id ?? null);
            $hmo = $record->hmo ?: ($patient->hmo ?? null);
            $schemeName = 'Self/Private';
            $hmoName = 'Self/Private';

            if ($hmoId && $hmoId != 1 && $hmo) {
                $schemeName = $hmo->scheme->name ?? 'Unknown Scheme';
                $hmoName = $hmo->name ?? 'Unknown HMO';
            }

            if (!isset($breakdown[$key]['schemes'][$schemeName])) {
                $breakdown[$key]['schemes'][$schemeName] = ['count' => 0, 'hmos' => []];
            }
            $breakdown[$key]['schemes'][$schemeName]['count']++;

            if (!isset($breakdown[$key]['schemes'][$schemeName]['hmos'][$hmoName])) {
                $breakdown[$key]['schemes'][$schemeName]['hmos'][$hmoName] = 0;
            }
            $breakdown[$key]['schemes'][$schemeName]['hmos'][$hmoName]++;
        };

        $processedPatients = [];
        $patientsByScheme = [];

        $posrQuery->with(['dispensedFromStore', 'patient.hmo.scheme', 'hmo.scheme', 'encounter.queue.clinic'])
            ->chunk(500, function ($posrRecords) use (
                &$collectionsByStore, &$incomeByScheme, &$totalGoodsUsed,
                &$visitTypeCounts, &$patientClassBreakdown, &$genderBreakdown,
                &$ageBreakdown, &$processedPatients, &$patientsByScheme,
                $ageBrackets, $addHmoData
            ) {
                foreach ($posrRecords as $record) {
                    // Part A: Financials
                    $storeName = $record->dispensedFromStore->store_name ?? 'Unknown Store';
                    $cashAmount = (float)$record->payable_amount;
                    $claimsAmount = (float)$record->claims_amount;
                    $amount = $cashAmount + $claimsAmount;
                    
                    $totalGoodsUsed += $amount;
                    
                    $patient = $record->patient;
                    $hmoId = $record->hmo_id ?: ($patient->hmo_id ?? null);
                    $hmo = $record->hmo ?: ($patient->hmo ?? null);
                    $schemeName = 'Self/Private';
                    $hmoName = 'Self/Private';

                    if ($hmoId && $hmoId != 1 && $hmo) {
                        $schemeName = $hmo->scheme->name ?? 'Unknown Scheme';
                        $hmoName = $hmo->name ?? 'Unknown HMO';
                    }

                    // Income by Scheme summary
                    if (!isset($incomeByScheme[$schemeName])) {
                        $incomeByScheme[$schemeName] = ['total' => 0, 'cash' => 0, 'claims' => 0];
                    }
                    $incomeByScheme[$schemeName]['total'] += $amount;
                    $incomeByScheme[$schemeName]['cash'] += $cashAmount;
                    $incomeByScheme[$schemeName]['claims'] += $claimsAmount;

                    if (!isset($collectionsByStore[$storeName])) {
                        $collectionsByStore[$storeName] = ['store_name' => $storeName, 'count' => 0, 'value' => 0, 'cash' => 0, 'claims' => 0, 'schemes' => []];
                    }
                    $collectionsByStore[$storeName]['count']++;
                    $collectionsByStore[$storeName]['value'] += $amount;
                    $collectionsByStore[$storeName]['cash'] += $cashAmount;
                    $collectionsByStore[$storeName]['claims'] += $claimsAmount;

                    if (!isset($collectionsByStore[$storeName]['schemes'][$schemeName])) {
                        $collectionsByStore[$storeName]['schemes'][$schemeName] = ['count' => 0, 'value' => 0, 'cash' => 0, 'claims' => 0, 'hmos' => []];
                    }
                    $collectionsByStore[$storeName]['schemes'][$schemeName]['count']++;
                    $collectionsByStore[$storeName]['schemes'][$schemeName]['value'] += $amount;
                    $collectionsByStore[$storeName]['schemes'][$schemeName]['cash'] += $cashAmount;
                    $collectionsByStore[$storeName]['schemes'][$schemeName]['claims'] += $claimsAmount;

                    if (!isset($collectionsByStore[$storeName]['schemes'][$schemeName]['hmos'][$hmoName])) {
                        $collectionsByStore[$storeName]['schemes'][$schemeName]['hmos'][$hmoName] = ['count' => 0, 'value' => 0, 'cash' => 0, 'claims' => 0];
                    }
                    $collectionsByStore[$storeName]['schemes'][$schemeName]['hmos'][$hmoName]['count']++;
                    $collectionsByStore[$storeName]['schemes'][$schemeName]['hmos'][$hmoName]['value'] += $amount;
                    $collectionsByStore[$storeName]['schemes'][$schemeName]['hmos'][$hmoName]['cash'] += $cashAmount;
                    $collectionsByStore[$storeName]['schemes'][$schemeName]['hmos'][$hmoName]['claims'] += $claimsAmount;

                    // Part B: Demographics
                    $patient = $record->patient;
                    if (!$patient) continue;
                    
                    // Only count patient demographics once per report run
                    if (isset($processedPatients[$patient->id])) continue;
                    $processedPatients[$patient->id] = true;
                    
                    $isAdmitted = $record->admission_request_id || ($record->encounter && $record->encounter->admission_request_id);
                    
                    if ($isAdmitted) {
                        $visitTypeCounts['Admitted']++;
                    } elseif ($record->encounter_id) {
                        $visitTypeCounts['Out-Patient']++;
                    } else {
                        $visitTypeCounts['Walk-in']++;
                    }

                    if (!isset($patientsByScheme[$schemeName])) {
                        $patientsByScheme[$schemeName] = 0;
                    }
                    $patientsByScheme[$schemeName]++;
                    
                    if ($isAdmitted) {
                        $addHmoData($patientClassBreakdown, 'Admitted', $record);
                    } elseif ($record->encounter_id) {
                        $addHmoData($patientClassBreakdown, 'Out-Patient', $record);
                    } else {
                        $addHmoData($patientClassBreakdown, 'Walk-in', $record);
                    }

                    $gender = $patient->gender ?? 'Unknown';
                    $addHmoData($genderBreakdown, $gender, $record);

                    $age = $patient->dob ? Carbon::parse($patient->dob)->age : null;
                    $ageLabel = 'Unknown';
                    if ($age !== null) {
                        foreach ($ageBrackets as $bracket) {
                            if ($age >= $bracket['min'] && $age <= $bracket['max']) {
                                $ageLabel = $bracket['label'];
                                break;
                            }
                        }
                    }
                    $addHmoData($ageBreakdown, $ageLabel, $record);
                }
            });

        // Calculate Opening Stock
        // Opening Stock = Closing Stock + Goods Used - Expenditure
        $openingStock = $totalStockValue + $totalGoodsUsed - $totalExpenditure;

        return [
            'stock_valuation' => $totalStockValue,
            'total_expenditure' => $totalExpenditure,
            'total_goods_used' => $totalGoodsUsed,
            'opening_stock' => $openingStock,
            'income_by_scheme' => $incomeByScheme,
            'patients_by_scheme' => $patientsByScheme,
            'collections_by_store' => array_values($collectionsByStore),
            'patients_attended_to' => $visitTypeCounts,
            'patient_classifications' => $patientClassBreakdown,
            'gender_distribution' => $genderBreakdown,
            'age_distribution' => $ageBreakdown,
            'age_brackets_used' => $ageBrackets
        ];
    }
}
