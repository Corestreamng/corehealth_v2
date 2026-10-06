<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PharmacyReturn;
use App\Models\ProductRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class PharmacyReturnsController extends Controller
{
    /**
     * Display returns list page or return stats for AJAX.
     */
    public function index(Request $request)
    {
        if ($request->ajax() && $request->has('stats_only')) {
            $total = PharmacyReturn::count();
            $pending = PharmacyReturn::where('status', 'pending')->count();
            $approved = PharmacyReturn::whereIn('status', ['approved', 'completed'])->count();
            $rejected = PharmacyReturn::where('status', 'rejected')->count();
            $completed = PharmacyReturn::where('status', 'completed')->count();
            $totalValue = PharmacyReturn::whereIn('status', ['approved', 'completed'])->sum('refund_amount');

            return response()->json([
                'stats' => [
                    'total' => $total,
                    'pending' => $pending,
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'completed' => $completed,
                    'total_value' => $totalValue,
                ],
            ]);
        }

        return view('admin.pharmacy.returns.index');
    }

    /**
     * Show form to create new return.
     */
    public function create()
    {
        return view('admin.pharmacy.returns.create');
    }

    /**
     * Search for dispensed items that can be returned.
     */
    public function searchDispensedItems(Request $request)
    {
        $query = ProductRequest::with([
            'product',
            'patient.user',
            'patient.hmo',
            'productOrServiceRequest.staff',
            'dispensedFromStore',
            'dispensedFromBatch',
            'biller',
            'dispenser',
            'pharmacyReturns',
        ])
            ->whereIn('status', [3, 4]) // Dispensed or legacy partial returned items
            ->where('is_free_form', 0) // Free form items cannot be returned
            ->whereNotNull('dispense_date')
            ->whereRaw('(product_requests.qty > COALESCE((SELECT SUM(pr_sub.qty_returned) FROM pharmacy_returns pr_sub WHERE pr_sub.product_request_id = product_requests.id AND pr_sub.status IN ("pending", "approved", "completed")), 0))');

        // Date filter: exact date takes precedence over date range
        if ($request->filled('date')) {
            $query->whereDate('dispense_date', $request->date);
        } else {
            if ($request->filled('start_date')) {
                $query->whereDate('dispense_date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('dispense_date', '<=', $request->end_date);
            }
        }

        // Patient filter (ID or name/file number text)
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        } elseif ($request->filled('patient_search')) {
            $term = $request->patient_search;
            $query->whereHas('patient', function ($pq) use ($term) {
                $pq->where('file_no', 'like', "%{$term}%")
                   ->orWhereHas('user', function ($uq) use ($term) {
                       $uq->where('firstname', 'like', "%{$term}%")
                          ->orWhere('surname', 'like', "%{$term}%")
                          ->orWhere('othername', 'like', "%{$term}%");
                   });
            });
        }

        return DataTables::of($query)
            ->filter(function ($q) use ($request) {
                if ($request->has('search') && !empty($request->search['value'])) {
                    $term = $request->search['value'];
                    $q->where(function ($outer) use ($term) {
                        $outer->whereHas('product', function ($q2) use ($term) {
                            $q2->where('product_name', 'like', "%{$term}%");
                        })
                        ->orWhereHas('patient.user', function ($q2) use ($term) {
                            $q2->where('firstname', 'like', "%{$term}%")
                               ->orWhere('surname', 'like', "%{$term}%")
                               ->orWhere('othername', 'like', "%{$term}%");
                        })
                        ->orWhereHas('patient', function ($q2) use ($term) {
                            $q2->where('file_no', 'like', "%{$term}%");
                        });
                    });
                }
            })
            ->addColumn('checkbox', function ($item) {
                $billReq = $item->productOrServiceRequest;
                $dispensedQty = (float)$item->qty;
                $alreadyReturned = (float)$item->pharmacyReturns
                    ->whereIn('status', ['pending', 'approved', 'completed'])
                    ->sum('qty_returned');
                $remainingQty = max(0, $dispensedQty - $alreadyReturned);

                $totalAmount = $billReq ? (float)($billReq->payable_amount + $billReq->claims_amount) : 0;
                $payable = $billReq ? (float)$billReq->payable_amount : 0;
                $claims = $billReq ? (float)$billReq->claims_amount : 0;

                $unitAmount = $dispensedQty > 0 ? $totalAmount / $dispensedQty : 0;
                $remainingAmount = $unitAmount * $remainingQty;
                $remainingPayable = $dispensedQty > 0 ? ($payable / $dispensedQty) * $remainingQty : 0;
                $remainingClaims = $dispensedQty > 0 ? ($claims / $dispensedQty) * $remainingQty : 0;

                $prod = htmlspecialchars($item->item_name ?? 'Unknown', ENT_QUOTES);
                $u = $item->patient->user ?? null;
                $nameParts = array_filter([$u->surname ?? '', $u->firstname ?? '', $u->othername ?? '']);
                $pat = htmlspecialchars(!empty($nameParts) ? implode(' ', $nameParts) : 'Unknown', ENT_QUOTES);
                $store = htmlspecialchars($item->dispensedFromStore->store_name ?? ($item->is_free_form ? 'External / NA' : 'Unknown Store'), ENT_QUOTES);
                $batchNo = htmlspecialchars($item->dispensedFromBatch->batch_number ?? 'N/A', ENT_QUOTES);
                $batchId = $item->dispensed_from_batch_id ?? '';
                $storeId = $item->dispensed_from_store_id ?? '';

                $dateStr = $item->dispense_date ? (is_string($item->dispense_date) ? Carbon::parse($item->dispense_date)->format('Y-m-d H:i:s') : $item->dispense_date->format('Y-m-d H:i:s')) : '';

                return "<input type='checkbox' class='dispensed-item-checkbox form-check-input position-static m-0' 
                    value='{$item->id}' 
                    data-id='{$item->id}' 
                    data-product='{$prod}' 
                    data-patient='{$pat}' 
                    data-qty='{$remainingQty}' 
                    data-dispensed-qty='{$dispensedQty}' 
                    data-already-returned='{$alreadyReturned}' 
                    data-amount='{$remainingAmount}' 
                    data-store='{$store}' 
                    data-store-id='{$storeId}' 
                    data-batch='{$batchNo}' 
                    data-batch-id='{$batchId}' 
                    data-payable='{$remainingPayable}' 
                    data-claims='{$remainingClaims}' 
                    data-date='{$dateStr}'>";
            })
            ->addColumn('patient', function ($item) {
                $u = $item->patient->user ?? null;
                $nameParts = array_filter([$u->surname ?? '', $u->firstname ?? '', $u->othername ?? '']);
                $name = htmlspecialchars(!empty($nameParts) ? implode(' ', $nameParts) : 'Unknown Patient', ENT_QUOTES);
                $file = htmlspecialchars($item->patient->file_no ?? '', ENT_QUOTES);
                $hmo = $item->patient && $item->patient->hmo ? htmlspecialchars($item->patient->hmo->name ?? 'HMO', ENT_QUOTES) : null;

                $html = "<div class='dense-patient-cell'>";
                $html .= "<div class='fw-bold text-dark text-truncate' style='max-width: 180px;' title='{$name}'>{$name}</div>";
                if ($file) {
                    $html .= "<div class='text-muted small'><i class='mdi mdi-folder-account-outline'></i> {$file}</div>";
                }
                if ($hmo) {
                    $html .= "<span class='badge badge-soft-info mt-1' style='font-size: 0.7rem; width: fit-content;'>{$hmo}</span>";
                }
                $html .= "</div>";

                return $html;
            })
            ->addColumn('product', function ($item) {
                $prod = htmlspecialchars($item->item_name ?? 'Unknown Product', ENT_QUOTES);
                $store = htmlspecialchars($item->dispensedFromStore->store_name ?? ($item->is_free_form ? 'External / NA' : 'Unknown Store'), ENT_QUOTES);
                $batch = htmlspecialchars($item->dispensedFromBatch->batch_number ?? '', ENT_QUOTES);
                $dispensedQty = (float)$item->qty;
                $alreadyReturned = (float)$item->pharmacyReturns
                    ->whereIn('status', ['pending', 'approved', 'completed'])
                    ->sum('qty_returned');

                $html = "<div class='dense-product-cell'>";
                $html .= "<div class='fw-bold text-primary text-truncate' style='max-width: 220px;' title='{$prod}'>{$prod}</div>";
                $html .= "<div class='d-flex flex-wrap gap-1 mt-1 small'>";
                $html .= "<span class='badge badge-light border text-muted py-0 px-1' title='Store'><i class='mdi mdi-store-outline'></i> {$store}</span>";
                if ($batch) {
                    $html .= "<span class='badge badge-light border text-dark py-0 px-1' title='Batch Number'><i class='mdi mdi-barcode'></i> {$batch}</span>";
                }
                if ($alreadyReturned > 0) {
                    $html .= "<span class='badge badge-soft-warning border-warning py-0 px-1' title='{$alreadyReturned} of {$dispensedQty} units already returned'><i class='mdi mdi-undo-variant'></i> Part. Returned ({$alreadyReturned}/{$dispensedQty})</span>";
                }
                $html .= "</div>";
                $html .= "</div>";

                return $html;
            })
            ->addColumn('audit', function ($item) {
                $billerUser = $item->biller ?? ($item->productOrServiceRequest->staff ?? null);
                if ($billerUser) {
                    $billerNameParts = array_filter([$billerUser->surname ?? '', $billerUser->firstname ?? '', $billerUser->othername ?? '']);
                    $billerName = !empty($billerNameParts) ? implode(' ', $billerNameParts) : 'Unknown';
                } elseif ($item->billed_by) {
                    $billerName = userfullname($item->billed_by);
                } else {
                    $billerName = 'Not Billed';
                }

                $dispenserUser = $item->dispenser;
                if ($dispenserUser) {
                    $dispenserNameParts = array_filter([$dispenserUser->surname ?? '', $dispenserUser->firstname ?? '', $dispenserUser->othername ?? '']);
                    $dispenserName = !empty($dispenserNameParts) ? implode(' ', $dispenserNameParts) : 'Unknown';
                } elseif ($item->dispensed_by) {
                    $dispenserName = userfullname($item->dispensed_by);
                } else {
                    $dispenserName = 'Unknown Staff';
                }

                $billedDate = $item->billed_date
                    ? Carbon::parse($item->billed_date)->format('M d, Y h:i A')
                    : ($item->productOrServiceRequest && $item->productOrServiceRequest->created_at
                        ? Carbon::parse($item->productOrServiceRequest->created_at)->format('M d, Y h:i A')
                        : '—');

                $dispensedDate = $item->dispense_date
                    ? Carbon::parse($item->dispense_date)->format('M d, Y h:i A')
                    : '—';

                $billerEsc = htmlspecialchars($billerName, ENT_QUOTES);
                $dispenserEsc = htmlspecialchars($dispenserName, ENT_QUOTES);

                $html = "<div class='dense-audit-cell small' style='line-height: 1.35;'>";
                $html .= "<div class='text-truncate' style='max-width: 250px;' title='Billed by {$billerEsc} on {$billedDate}'>";
                $html .= "<i class='mdi mdi-receipt text-primary me-1'></i><span class='text-muted'>Billed:</span> <span class='fw-semibold text-dark'>{$billerEsc}</span>";
                $html .= "<span class='text-muted ms-1' style='font-size: 0.78rem;'>({$billedDate})</span>";
                $html .= "</div>";
                $html .= "<div class='text-truncate mt-1' style='max-width: 250px;' title='Dispensed by {$dispenserEsc} on {$dispensedDate}'>";
                $html .= "<i class='mdi mdi-pill text-success me-1'></i><span class='text-muted'>Dispensed:</span> <span class='fw-semibold text-dark'>{$dispenserEsc}</span>";
                $html .= "<span class='text-muted ms-1' style='font-size: 0.78rem;'>({$dispensedDate})</span>";
                $html .= "</div>";
                $html .= "</div>";

                return $html;
            })
            ->addColumn('action', function ($item) {
                $billReq = $item->productOrServiceRequest;
                $dispensedQty = (float)$item->qty;
                $alreadyReturned = (float)$item->pharmacyReturns
                    ->whereIn('status', ['pending', 'approved', 'completed'])
                    ->sum('qty_returned');
                $remainingQty = max(0, $dispensedQty - $alreadyReturned);

                $totalAmount = $billReq ? (float)($billReq->payable_amount + $billReq->claims_amount) : 0;
                $payable = $billReq ? (float)$billReq->payable_amount : 0;
                $claims = $billReq ? (float)$billReq->claims_amount : 0;

                $unitAmount = $dispensedQty > 0 ? $totalAmount / $dispensedQty : 0;
                $remainingAmount = $unitAmount * $remainingQty;
                $remainingPayable = $dispensedQty > 0 ? ($payable / $dispensedQty) * $remainingQty : 0;
                $remainingClaims = $dispensedQty > 0 ? ($claims / $dispensedQty) * $remainingQty : 0;

                $prod = htmlspecialchars($item->item_name ?? 'Unknown', ENT_QUOTES);
                $u = $item->patient->user ?? null;
                $nameParts = array_filter([$u->surname ?? '', $u->firstname ?? '', $u->othername ?? '']);
                $pat = htmlspecialchars(!empty($nameParts) ? implode(' ', $nameParts) : 'Unknown', ENT_QUOTES);
                $store = htmlspecialchars($item->dispensedFromStore->store_name ?? ($item->is_free_form ? 'External / NA' : 'Unknown Store'), ENT_QUOTES);
                $batchNo = htmlspecialchars($item->dispensedFromBatch->batch_number ?? 'N/A', ENT_QUOTES);
                $batchId = $item->dispensed_from_batch_id ?? '';
                $storeId = $item->dispensed_from_store_id ?? '';

                $dateStr = $item->dispense_date ? (is_string($item->dispense_date) ? Carbon::parse($item->dispense_date)->format('Y-m-d H:i:s') : $item->dispense_date->format('Y-m-d H:i:s')) : '';

                $fmtRemainingAmount = number_format($remainingAmount, 2);
                $splitText = $remainingClaims > 0 ? "Pat: ₦" . number_format($remainingPayable, 2) . " | HMO: ₦" . number_format($remainingClaims, 2) : "Patient: 100%";

                $html = "<div class='dense-action-cell text-end'>";
                $html .= "<div class='d-flex justify-content-between align-items-center mb-1'>";
                if ($alreadyReturned > 0) {
                    $html .= "<div><span class='badge badge-success px-2 py-0' style='font-size: 0.78rem;' title='{$remainingQty} of {$dispensedQty} units remaining'>{$remainingQty} rem.</span> <span class='badge badge-soft-warning px-1 py-0' style='font-size: 0.7rem;'>{$alreadyReturned} ret.</span></div>";
                } else {
                    $html .= "<span class='badge badge-primary px-2 py-0' style='font-size: 0.78rem;'>{$dispensedQty} units</span>";
                }
                $html .= "<span class='fw-bold text-success' style='font-size: 0.95rem;'>₦{$fmtRemainingAmount}</span>";
                $html .= "</div>";
                $html .= "<div class='text-muted mb-2' style='font-size: 0.74rem;'>{$splitText}</div>";
                $html .= "<button class='btn btn-xs btn-outline-primary return-item-select w-100 py-1' 
                    data-id='{$item->id}' 
                    data-product='{$prod}' 
                    data-patient='{$pat}' 
                    data-qty='{$remainingQty}' 
                    data-dispensed-qty='{$dispensedQty}' 
                    data-already-returned='{$alreadyReturned}' 
                    data-amount='{$remainingAmount}' 
                    data-store='{$store}' 
                    data-store-id='{$storeId}' 
                    data-batch='{$batchNo}' 
                    data-batch-id='{$batchId}' 
                    data-payable='{$remainingPayable}' 
                    data-claims='{$remainingClaims}' 
                    data-date='{$dateStr}'>
                    <i class='mdi mdi-plus-circle-outline'></i> Select
                </button>";
                $html .= "</div>";

                return $html;
            })
            ->rawColumns(['checkbox', 'patient', 'product', 'audit', 'action'])
            ->make(true);
    }

    /**
     * Store a new return.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_request_id' => 'required|exists:product_requests,id',
            'qty_returned' => 'required|numeric|min:0.01',
            'return_condition' => 'required|in:good,damaged,expired,wrong_item',
            'return_reason' => 'required|string|min:3',
        ]);

        $productRequest = ProductRequest::with(['productOrServiceRequest', 'patient', 'product'])
            ->findOrFail($request->product_request_id);

        // Calculate already returned quantity for this dispensed item
        $alreadyReturned = (float) PharmacyReturn::where('product_request_id', $productRequest->id)
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->sum('qty_returned');

        $availableQty = max(0, (float)$productRequest->qty - $alreadyReturned);

        if ($availableQty <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This dispensed item has already been fully returned.',
            ], 422);
        }

        if ((float)$request->qty_returned > $availableQty) {
            return response()->json([
                'success' => false,
                'message' => "Return quantity ({$request->qty_returned}) cannot exceed remaining returnable quantity ({$availableQty}).",
            ], 422);
        }

        try {
            DB::beginTransaction();

            $billRequest = $productRequest->productOrServiceRequest;
            if (!$billRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Billing record not found for this item',
                ], 404);
            }

            // Calculate refund amount proportional to dispensed qty
            $totalAmount = (float)($billRequest->payable_amount + $billRequest->claims_amount);
            $unitAmount = $productRequest->qty > 0 ? $totalAmount / $productRequest->qty : 0;
            $refundAmount = $unitAmount * (float)$request->qty_returned;

            // Split refund for HMO patients
            $refundToPatient = $totalAmount > 0
                ? ($billRequest->payable_amount / $totalAmount) * $refundAmount
                : 0;
            $refundToHmo = $totalAmount > 0
                ? ($billRequest->claims_amount / $totalAmount) * $refundAmount
                : 0;

            // Determine if item can be restocked
            // 'good' and 'wrong_item' are restockable conditions
            $restock = in_array($request->return_condition, ['good', 'wrong_item']);

            // Create return record
            $return = PharmacyReturn::create([
                'product_request_id' => $productRequest->id,
                'product_or_service_request_id' => $billRequest->id,
                'patient_id' => $productRequest->patient_id,
                'product_id' => $productRequest->product_id,
                'store_id' => $productRequest->dispensed_from_store_id,
                'batch_id' => $productRequest->dispensed_from_batch_id,
                'qty_returned' => $request->qty_returned,
                'original_qty' => $productRequest->qty,
                'refund_amount' => $refundAmount,
                'original_amount' => $totalAmount,
                'return_condition' => $request->return_condition,
                'return_reason' => $request->return_reason,
                'restock' => $restock,
                'refund_to_patient' => $refundToPatient,
                'refund_to_hmo' => $refundToHmo,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);

            // Update ProductRequest status: 4 if fully returned, 3 if partial
            $newTotalReturned = $alreadyReturned + (float)$request->qty_returned;
            $isFullyReturned = ($newTotalReturned >= (float)$productRequest->qty);

            $productRequest->update([
                'status' => $isFullyReturned ? 4 : 3,
                'returned_by' => Auth::id(),
                'returned_date' => now(),
                'returned_qty' => $newTotalReturned,
                'refund_amount' => ($unitAmount * $newTotalReturned),
                'return_reason' => $request->return_reason,
                'return_condition' => $request->return_condition,
            ]);

            DB::commit();

            Log::info('PharmacyReturn created', [
                'return_id' => $return->id,
                'product_request_id' => $productRequest->id,
                'qty_returned' => $request->qty_returned,
                'refund_amount' => $refundAmount,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Return request created successfully. Awaiting approval.',
                'return' => $return,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create pharmacy return', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create return: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store multiple returns in a single atomic transaction.
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_request_id' => 'required|exists:product_requests,id',
            'items.*.qty_returned' => 'required|numeric|min:0.01',
            'items.*.return_condition' => 'required|in:good,damaged,expired,wrong_item',
            'items.*.return_reason' => 'required|string|min:3',
        ], [
            'items.required' => 'Please select at least one item to return.',
            'items.*.qty_returned.min' => 'Return quantity must be greater than zero.',
            'items.*.return_reason.min' => 'Return reason must be at least 3 characters for each item.',
        ]);

        try {
            DB::beginTransaction();

            $createdReturns = [];
            $totalRefund = 0;

            foreach ($request->items as $index => $itemData) {
                $productRequestId = $itemData['product_request_id'];
                $qtyReturned = (float)$itemData['qty_returned'];
                $condition = $itemData['return_condition'];
                $reason = $itemData['return_reason'];

                $productRequest = ProductRequest::with(['productOrServiceRequest', 'patient', 'product'])
                    ->findOrFail($productRequestId);

                $productName = $productRequest->product->product_name ?? 'Item #' . ($index + 1);

                // Calculate already returned quantity for this dispensed item
                $alreadyReturned = (float) PharmacyReturn::where('product_request_id', $productRequest->id)
                    ->whereIn('status', ['pending', 'approved', 'completed'])
                    ->sum('qty_returned');

                $availableQty = max(0, (float)$productRequest->qty - $alreadyReturned);

                if ($availableQty <= 0) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => "Item '{$productName}' has already been fully returned.",
                    ], 422);
                }

                if ($qtyReturned > $availableQty) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => "Return quantity for '{$productName}' ({$qtyReturned}) cannot exceed remaining returnable quantity ({$availableQty}).",
                    ], 422);
                }

                $billRequest = $productRequest->productOrServiceRequest;
                if (!$billRequest) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => "Billing record not found for item #" . ($index + 1) . ".",
                    ], 404);
                }

                // Calculate proportional refund
                $totalAmount = (float)($billRequest->payable_amount + $billRequest->claims_amount);
                $unitAmount = $productRequest->qty > 0 ? $totalAmount / $productRequest->qty : 0;
                $refundAmount = $unitAmount * $qtyReturned;

                $refundToPatient = $totalAmount > 0
                    ? ($billRequest->payable_amount / $totalAmount) * $refundAmount
                    : 0;
                $refundToHmo = $totalAmount > 0
                    ? ($billRequest->claims_amount / $totalAmount) * $refundAmount
                    : 0;

                $restock = in_array($condition, ['good', 'wrong_item']);

                $return = PharmacyReturn::create([
                    'product_request_id' => $productRequest->id,
                    'product_or_service_request_id' => $billRequest->id,
                    'patient_id' => $productRequest->patient_id,
                    'product_id' => $productRequest->product_id,
                    'store_id' => $productRequest->dispensed_from_store_id,
                    'batch_id' => $productRequest->dispensed_from_batch_id,
                    'qty_returned' => $qtyReturned,
                    'original_qty' => $productRequest->qty,
                    'refund_amount' => $refundAmount,
                    'original_amount' => $totalAmount,
                    'return_condition' => $condition,
                    'return_reason' => $reason,
                    'restock' => $restock,
                    'refund_to_patient' => $refundToPatient,
                    'refund_to_hmo' => $refundToHmo,
                    'status' => 'pending',
                    'created_by' => Auth::id(),
                ]);

                // Update ProductRequest status: 4 if fully returned, 3 if partial
                $newTotalReturned = $alreadyReturned + $qtyReturned;
                $isFullyReturned = ($newTotalReturned >= (float)$productRequest->qty);

                $productRequest->update([
                    'status' => $isFullyReturned ? 4 : 3,
                    'returned_by' => Auth::id(),
                    'returned_date' => now(),
                    'returned_qty' => $newTotalReturned,
                    'refund_amount' => ($unitAmount * $newTotalReturned),
                    'return_reason' => $reason,
                    'return_condition' => $condition,
                ]);

                $createdReturns[] = $return;
                $totalRefund += $refundAmount;
            }

            DB::commit();

            Log::info('PharmacyReturn: Bulk returns created', [
                'count' => count($createdReturns),
                'total_refund' => $totalRefund,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully submitted ' . count($createdReturns) . ' return(s) for approval.',
                'count' => count($createdReturns),
                'total_refund' => $totalRefund,
                'returns' => $createdReturns,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create bulk pharmacy returns', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk returns: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show return details.
     */
    public function show($id, Request $request)
    {
        $return = PharmacyReturn::with([
            'productRequest',
            'billRequest',
            'patient.user',
            'product',
            'store',
            'batch',
            'creator',
            'approver',
            'journalEntry.lines.account',
        ])->findOrFail($id);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'return' => [
                    'id' => $return->id,
                    'patient_name' => $return->patient->user->name ?? 'N/A',
                    'file_no' => $return->patient->file_no ?? '',
                    'product_name' => $return->product->product_name ?? 'N/A',
                    'product_id' => $return->product_id,
                    'store_name' => $return->store->store_name ?? 'N/A',
                    'store_id' => $return->store_id,
                    'batch_number' => $return->batch->batch_number ?? 'N/A',
                    'batch_id' => $return->batch_id,
                    'qty_returned' => $return->qty_returned,
                    'original_qty' => $return->original_qty,
                    'refund_amount' => $return->refund_amount,
                    'original_amount' => $return->original_amount,
                    'refund_to_patient' => $return->refund_to_patient,
                    'refund_to_hmo' => $return->refund_to_hmo,
                    'return_condition' => $return->return_condition,
                    'return_reason' => $return->return_reason,
                    'restock' => $return->restock,
                    'status' => $return->status,
                    'created_by' => $return->creator->name ?? 'N/A',
                    'approved_by' => $return->approver->name ?? null,
                    'approval_notes' => $return->approval_notes,
                    'created_at' => $return->created_at->format('M d, Y h:i A'),
                    'approved_at' => $return->approved_at ? $return->approved_at->format('M d, Y h:i A') : null,
                    'journal_entry' => $return->journalEntry ? [
                        'id' => $return->journalEntry->id,
                        'reference' => $return->journalEntry->reference ?? 'JE-' . $return->journalEntry->id,
                        'description' => $return->journalEntry->description,
                        'status' => $return->journalEntry->status ?? null,
                        'lines' => $return->journalEntry->lines->map(function ($line) {
                            return [
                                'account_name' => $line->account->name ?? 'N/A',
                                'account_code' => $line->account->code ?? '',
                                'debit' => $line->debit_amount,
                                'credit' => $line->credit_amount,
                                'description' => $line->description,
                            ];
                        }),
                    ] : null,
                ],
            ]);
        }

        return view('admin.pharmacy.returns.show', compact('return'));
    }

    /**
     * Approve a return (unit head/manager).
     */
    public function approve($id, Request $request)
    {
        $request->validate([
            'approval_notes' => 'nullable|string',
        ]);

        try {
            $return = PharmacyReturn::findOrFail($id);

            if ($return->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending returns can be approved',
                ], 422);
            }

            $return->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes,
            ]);

            // Observer will create journal entry automatically
            // Auto-restock for good condition items (WF-2)
            // Uses StockBatch::addStock() which records a transaction and triggers
            // StockBatchObserver → syncStoreStock() → auto-syncs store_stocks + global stocks
            $restocked = false;
            if ($return->restock && $return->batch_id) {
                $batch = $return->batch;
                if ($batch) {
                    $batch->addStock(
                        $return->qty_returned,
                        'return',
                        PharmacyReturn::class,
                        $return->id,
                        "Return #{$return->id} - Restocking {$return->qty_returned} units"
                    );
                    // StockBatchObserver auto-syncs store_stocks and global stocks
                    $restocked = true;
                }
            }

            // Credit patient wallet with refund amount
            // The JE was already created by PharmacyReturnObserver (CR: Customer Deposits 2200).
            // We create an ACC_DEPOSIT payment record for statement visibility only,
            // linking it to the return's JE so PaymentObserver skips duplicate JE creation.
            $refundToPatient = $return->refund_to_patient > 0
                ? $return->refund_to_patient
                : $return->refund_amount;

            // Reload to get JE created by observer
            $return->refresh();
            $return->load(['journalEntry', 'product']);

            if ($refundToPatient > 0) {
                $productName = $return->product->product_name ?? 'Unknown';

                // 1. Update wallet balance
                $patientAccount = \App\Models\PatientAccount::firstOrCreate(
                    ['patient_id' => $return->patient_id],
                    ['balance' => 0]
                );
                $patientAccount->increment('balance', $refundToPatient);

                // 2. Create ACC_DEPOSIT payment for patient statement visibility
                //    Link to existing JE so PaymentObserver does NOT create a duplicate
                $refNo = generate_invoice_no();
                \App\Models\Payment::create([
                    'patient_id' => $return->patient_id,
                    'user_id' => Auth::id(),
                    'total' => $refundToPatient,
                    'reference_no' => $refNo,
                    'payment_type' => 'ACC_DEPOSIT',
                    'payment_method' => 'REFUND',
                    'journal_entry_id' => $return->journal_entry_id, // Link to return JE — prevents duplicate
                ]);

                Log::info('PharmacyReturn: Patient wallet credited via ACC_DEPOSIT', [
                    'return_id' => $return->id,
                    'patient_id' => $return->patient_id,
                    'amount_credited' => $refundToPatient,
                    'payment_ref' => $refNo,
                    'linked_je_id' => $return->journal_entry_id,
                ]);
            }

            // Once approved, restocked/written off, and wallet refunded, mark completed
            $return->update(['status' => 'completed']);

            Log::info('PharmacyReturn approved', [
                'return_id' => $return->id,
                'approved_by' => Auth::id(),
                'auto_restocked' => $restocked,
            ]);

            $message = 'Return approved successfully.';
            if ($restocked) {
                $message .= ' Stock restocked automatically.';
            }
            if ($refundToPatient > 0) {
                $message .= ' ₦' . number_format($refundToPatient, 2) . ' credited to patient wallet.';
            }
            if ($return->journalEntry) {
                $message .= ' JE Ref: ' . ($return->journalEntry->reference ?? 'JE-' . $return->journalEntry->id);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'journal_entry_id' => $return->journal_entry_id,
                'auto_restocked' => $restocked,
                'wallet_credited' => $refundToPatient,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to approve return', [
                'return_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve return: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a return.
     */
    public function reject($id, Request $request)
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:10',
        ]);

        try {
            $return = PharmacyReturn::findOrFail($id);

            if ($return->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending returns can be rejected',
                ], 422);
            }

            $return->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'approval_notes' => $request->rejection_reason,
            ]);

            // Revert or update ProductRequest status
            $productRequest = $return->productRequest;
            if ($productRequest) {
                $remainingActiveReturns = PharmacyReturn::where('product_request_id', $productRequest->id)
                    ->where('id', '!=', $return->id)
                    ->whereIn('status', ['pending', 'approved', 'completed'])
                    ->get();
                $newActiveQty = (float)$remainingActiveReturns->sum('qty_returned');
                $newActiveRefund = (float)$remainingActiveReturns->sum('refund_amount');

                $productRequest->update([
                    'status' => ($newActiveQty >= (float)$productRequest->qty && $newActiveQty > 0) ? 4 : 3,
                    'returned_qty' => $newActiveQty > 0 ? $newActiveQty : null,
                    'refund_amount' => $newActiveRefund > 0 ? $newActiveRefund : null,
                    'returned_by' => $newActiveQty > 0 ? $productRequest->returned_by : null,
                    'returned_date' => $newActiveQty > 0 ? $productRequest->returned_date : null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Return rejected.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reject return', [
                'return_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject return: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process refund (mark as completed after payment).
     */
    public function processRefund($id)
    {
        try {
            $return = PharmacyReturn::findOrFail($id);

            if ($return->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved returns can be refunded',
                ], 422);
            }

            // Restock if condition is good
            if ($return->restock && $return->batch_id) {
                $batch = $return->batch;
                if ($batch) {
                    $batch->increment('current_qty', $return->qty_returned);
                    Log::info('Stock restocked from return', [
                        'return_id' => $return->id,
                        'batch_id' => $return->batch_id,
                        'qty_restocked' => $return->qty_returned,
                    ]);
                }
            }

            $return->update(['status' => 'completed']);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process refund', [
                'return_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk approve pending returns.
     */
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'return_ids' => 'required|array|min:1',
            'return_ids.*' => 'required|integer|exists:pharmacy_returns,id',
            'approval_notes' => 'nullable|string',
        ], [
            'return_ids.required' => 'Please select at least one return to approve.',
        ]);

        $approvedCount = 0;
        $totalRestocked = 0;
        $totalRefund = 0;
        $errors = [];

        foreach ($request->return_ids as $id) {
            try {
                $return = PharmacyReturn::find($id);
                if (!$return || $return->status !== 'pending') {
                    $errors[] = "Return #{$id} is not in pending status.";

                    continue;
                }

                $return->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'approval_notes' => $request->approval_notes,
                ]);

                // Observer will create journal entry automatically
                // Auto-restock for good condition items
                $restocked = false;
                if ($return->restock && $return->batch_id) {
                    $batch = $return->batch;
                    if ($batch) {
                        $batch->addStock(
                            $return->qty_returned,
                            'return',
                            PharmacyReturn::class,
                            $return->id,
                            "Return #{$return->id} - Restocking {$return->qty_returned} units"
                        );
                        $restocked = true;
                        $totalRestocked++;
                    }
                }

                // Credit patient wallet with refund amount
                $refundToPatient = $return->refund_to_patient > 0
                    ? $return->refund_to_patient
                    : $return->refund_amount;

                $return->refresh();

                if ($refundToPatient > 0) {
                    $patientAccount = \App\Models\PatientAccount::firstOrCreate(
                        ['patient_id' => $return->patient_id],
                        ['balance' => 0]
                    );
                    $patientAccount->increment('balance', $refundToPatient);

                    $refNo = generate_invoice_no();
                    \App\Models\Payment::create([
                        'patient_id' => $return->patient_id,
                        'user_id' => Auth::id(),
                        'total' => $refundToPatient,
                        'reference_no' => $refNo,
                        'payment_type' => 'ACC_DEPOSIT',
                        'payment_method' => 'REFUND',
                        'journal_entry_id' => $return->journal_entry_id,
                    ]);
                }

                // Once approved, restocked/written off, and wallet refunded, mark completed
                $return->update(['status' => 'completed']);

                $approvedCount++;
                $totalRefund += $return->refund_amount;

            } catch (\Exception $e) {
                Log::error("Failed to approve return #{$id}", ['error' => $e->getMessage()]);
                $errors[] = "Return #{$id}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => $approvedCount > 0,
            'message' => "Successfully approved {$approvedCount} return(s)." . (!empty($errors) ? ' Some errors occurred: ' . implode('; ', $errors) : ''),
            'approved_count' => $approvedCount,
            'total_refund' => $totalRefund,
            'total_restocked' => $totalRestocked,
            'errors' => $errors,
        ]);
    }

    /**
     * Bulk reject pending returns.
     */
    public function bulkReject(Request $request)
    {
        $request->validate([
            'return_ids' => 'required|array|min:1',
            'return_ids.*' => 'required|integer|exists:pharmacy_returns,id',
            'rejection_reason' => 'required|string|min:10',
        ], [
            'return_ids.required' => 'Please select at least one return to reject.',
            'rejection_reason.min' => 'Rejection reason must be at least 10 characters.',
        ]);

        $rejectedCount = 0;
        $errors = [];

        foreach ($request->return_ids as $id) {
            try {
                $return = PharmacyReturn::find($id);
                if (!$return || $return->status !== 'pending') {
                    $errors[] = "Return #{$id} is not in pending status.";

                    continue;
                }

                $return->update([
                    'status' => 'rejected',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'approval_notes' => $request->rejection_reason,
                ]);

                // Revert or update ProductRequest status
                if ($return->productRequest) {
                    $remainingActiveReturns = PharmacyReturn::where('product_request_id', $return->product_request_id)
                        ->where('id', '!=', $return->id)
                        ->whereIn('status', ['pending', 'approved', 'completed'])
                        ->get();
                    $newActiveQty = (float)$remainingActiveReturns->sum('qty_returned');
                    $newActiveRefund = (float)$remainingActiveReturns->sum('refund_amount');

                    $return->productRequest->update([
                        'status' => ($newActiveQty >= (float)$return->productRequest->qty && $newActiveQty > 0) ? 4 : 3,
                        'returned_qty' => $newActiveQty > 0 ? $newActiveQty : null,
                        'refund_amount' => $newActiveRefund > 0 ? $newActiveRefund : null,
                        'returned_by' => $newActiveQty > 0 ? $return->productRequest->returned_by : null,
                        'returned_date' => $newActiveQty > 0 ? $return->productRequest->returned_date : null,
                    ]);
                }

                $rejectedCount++;

            } catch (\Exception $e) {
                Log::error("Failed to reject return #{$id}", ['error' => $e->getMessage()]);
                $errors[] = "Return #{$id}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => $rejectedCount > 0,
            'message' => "Successfully rejected {$rejectedCount} return(s).",
            'rejected_count' => $rejectedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * DataTables endpoint for returns list.
     */
    public function datatables(Request $request)
    {
        $query = PharmacyReturn::with([
            'patient.user',
            'product',
            'store',
            'creator',
            'approver',
        ]);

        // Filters
        if ($request->has('status') && $request->status != '') {
            if ($request->status === 'approved') {
                $query->whereIn('status', ['approved', 'completed']);
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('checkbox', function ($return) {
                if ($return->status === 'pending') {
                    return '<input type="checkbox" class="pending-return-checkbox form-check-input position-static m-0" value="' . $return->id . '" data-id="' . $return->id . '">';
                }

                return '<input type="checkbox" class="form-check-input position-static m-0" disabled data-id="' . $return->id . '">';
            })
            ->addColumn('patient_name', function ($return) {
                return $return->patient->user->name ?? 'N/A';
            })
            ->addColumn('product_name', function ($return) {
                return $return->product->product_name ?? 'N/A';
            })
            ->addColumn('store_name', function ($return) {
                return $return->store->store_name ?? 'N/A';
            })
            ->addColumn('created_by_name', function ($return) {
                return $return->creator->name ?? 'N/A';
            })
            ->addColumn('approved_by_name', function ($return) {
                return $return->approver->name ?? 'N/A';
            })
            ->addColumn('item_info', function ($return) {
                $patient = e($return->patient->user->name ?? 'N/A');
                $product = e($return->product->product_name ?? 'N/A');

                return '<strong>' . $product . '</strong>'
                    . '<br><small class="text-muted"><i class="mdi mdi-account"></i> ' . $patient . '</small>';
            })
            ->addColumn('details_info', function ($return) {
                $date = $return->created_at ? $return->created_at->format('M d, Y') : '-';
                $refund = '₦' . number_format($return->refund_amount, 2);

                return '<span class="font-weight-bold">' . $return->qty_returned . '</span> returned'
                    . '<br><small class="text-success">' . $refund . '</small>'
                    . '<br><small class="text-muted">' . $date . '</small>';
            })
            ->addColumn('status_info', function ($return) {
                $condBadges = [
                    'good' => '<span class="badge badge-success">Good</span>',
                    'damaged' => '<span class="badge badge-danger">Damaged</span>',
                    'expired' => '<span class="badge badge-warning">Expired</span>',
                    'wrong_item' => '<span class="badge badge-info">Wrong</span>',
                ];
                $statusBadges = [
                    'pending' => '<span class="badge badge-warning">Pending</span>',
                    'approved' => '<span class="badge badge-success">Approved</span>',
                    'rejected' => '<span class="badge badge-danger">Rejected</span>',
                    'completed' => '<span class="badge badge-info">Completed</span>',
                ];
                $cond = $condBadges[$return->return_condition] ?? $return->return_condition;
                $status = $statusBadges[$return->status] ?? $return->status;

                return $cond . ' ' . $status;
            })
            ->addColumn('condition_badge', function ($return) {
                $badges = [
                    'good' => '<span class="badge badge-success">Good</span>',
                    'damaged' => '<span class="badge badge-danger">Damaged</span>',
                    'expired' => '<span class="badge badge-warning">Expired</span>',
                    'wrong_item' => '<span class="badge badge-info">Wrong Item</span>',
                ];

                return $badges[$return->return_condition] ?? $return->return_condition;
            })
            ->addColumn('status_badge', function ($return) {
                $badges = [
                    'pending' => '<span class="badge badge-warning">Pending</span>',
                    'approved' => '<span class="badge badge-success">Approved</span>',
                    'rejected' => '<span class="badge badge-danger">Rejected</span>',
                    'completed' => '<span class="badge badge-info">Completed</span>',
                ];

                return $badges[$return->status] ?? $return->status;
            })
            ->addColumn('actions', function ($return) {
                $html = '<div class="btn-group">';
                $html .= '<button class="btn btn-sm btn-info btn-view-return" data-id="' . $return->id . '" title="View Details"><i class="mdi mdi-eye"></i></button>';

                if ($return->status === 'pending') {
                    $html .= '<button class="btn btn-sm btn-success btn-approve-return" data-id="' . $return->id . '" title="Approve"><i class="mdi mdi-check"></i></button>';
                    $html .= '<button class="btn btn-sm btn-danger btn-reject-return" data-id="' . $return->id . '" title="Reject"><i class="mdi mdi-close"></i></button>';
                }

                $html .= '</div>';

                return $html;
            })
            ->rawColumns(['checkbox', 'condition_badge', 'status_badge', 'item_info', 'details_info', 'status_info', 'actions'])
            ->make(true);
    }
}
