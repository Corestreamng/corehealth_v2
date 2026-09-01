<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReturn;
use App\Models\StockBatch;
use App\Models\StoreStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class PurchaseOrderReturnController extends Controller
{
    /**
     * Store a new PO return record.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'batch_id' => 'nullable|exists:stock_batches,id',
            'qty_returned' => 'required|integer|min:1',
            'unit_cost' => 'required|numeric|min:0',
            'return_reason' => 'required|in:wrong_item,damaged,excess,quality_issue,other',
            'return_notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $po = PurchaseOrder::findOrFail($validated['purchase_order_id']);
            $item = PurchaseOrderItem::findOrFail($validated['purchase_order_item_id']);

            if ($item->purchase_order_id !== $po->id) {
                return response()->json(['success' => false, 'message' => 'Item does not belong to this PO'], 422);
            }

            // Must have been at least partially received
            if (!in_array($po->status, [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED])) {
                return response()->json(['success' => false, 'message' => 'PO must be partially or fully received before returning items'], 422);
            }

            // Check already-returned qty
            $alreadyReturned = PurchaseOrderReturn::where('purchase_order_item_id', $item->id)
                ->whereIn('status', ['pending', 'approved'])
                ->sum('qty_returned');
            $maxReturnable = $item->received_qty - $alreadyReturned;

            if ($validated['qty_returned'] > $maxReturnable) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot return {$validated['qty_returned']}. Max returnable: {$maxReturnable}",
                ], 422);
            }

            // Stock check
            if (!empty($validated['batch_id'])) {
                $batch = StockBatch::findOrFail($validated['batch_id']);
                if ($batch->current_qty < $validated['qty_returned']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Batch has only ' . $batch->current_qty . ' available',
                    ], 422);
                }
            } else {
                $storeStock = StoreStock::where('product_id', $item->product_id)
                    ->where('store_id', $po->target_store_id)
                    ->first();
                $available = $storeStock ? $storeStock->current_quantity : 0;
                if ($available < $validated['qty_returned']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock. Available: ' . $available,
                    ], 422);
                }
            }

            $validated['product_id'] = $item->product_id;
            $validated['store_id'] = $po->target_store_id;
            $validated['total_value'] = $validated['qty_returned'] * $validated['unit_cost'];
            $validated['payment_status_at_return'] = $po->payment_status;
            $validated['created_by'] = Auth::id();
            $validated['status'] = 'pending';
            $validated['return_number'] = PurchaseOrderReturn::generateReturnNumber();

            $return = PurchaseOrderReturn::create($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Return recorded. Awaiting approval.',
                'return_id' => $return->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PurchaseOrderReturn store error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show a return record (AJAX or full page).
     */
    public function show(Request $request, $id)
    {
        $return = PurchaseOrderReturn::with([
            'purchaseOrder.supplier',
            'purchaseOrderItem.product',
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
                    'return_number' => $return->return_number,
                    'po_number' => $return->purchaseOrder->po_number ?? 'N/A',
                    'supplier' => $return->purchaseOrder->supplier->supplier_name ?? 'N/A',
                    'product_name' => $return->product->product_name ?? 'N/A',
                    'store_name' => $return->store->store_name ?? 'N/A',
                    'batch_number' => $return->batch->batch_number ?? 'N/A',
                    'qty_returned' => $return->qty_returned,
                    'unit_cost' => $return->unit_cost,
                    'total_value' => $return->total_value,
                    'return_reason' => $return->return_reason,
                    'return_notes' => $return->return_notes,
                    'payment_status_at_return' => $return->payment_status_at_return,
                    'status' => $return->status,
                    'expense_adjusted' => $return->expense_adjusted,
                    'stock_deducted' => $return->stock_deducted,
                    'created_by' => $return->creator->name ?? 'N/A',
                    'approved_by' => $return->approver->name ?? null,
                    'approval_notes' => $return->approval_notes,
                    'created_at' => $return->created_at->format('M d, Y h:i A'),
                    'approved_at' => $return->approved_at ? $return->approved_at->format('M d, Y h:i A') : null,
                    'financial_context' => [
                        'payment_status' => $return->payment_status_at_return ?: ($return->purchaseOrder->payment_status ?? 'unpaid'),
                        'po_total_amount' => (float)($return->purchaseOrder->total_amount ?? 0),
                        'po_amount_paid' => (float)($return->purchaseOrder->amount_paid ?? 0),
                        'po_balance_due' => (float)($return->purchaseOrder->balance_due ?? 0),
                        'is_fully_paid' => ($return->payment_status_at_return === 'paid' || ($return->purchaseOrder && $return->purchaseOrder->payment_status === 'paid')),
                        'dr_account_code' => ($return->payment_status_at_return === 'paid' || ($return->purchaseOrder && $return->purchaseOrder->payment_status === 'paid')) ? '1200' : '2110',
                        'dr_account_name' => ($return->payment_status_at_return === 'paid' || ($return->purchaseOrder && $return->purchaseOrder->payment_status === 'paid')) ? 'Accounts Receivable (1200) — Supplier Credit' : 'Accounts Payable - Suppliers (2110) — Debt Reduction',
                        'cr_account_code' => '1310',
                        'cr_account_name' => 'Inventory - Medical Supplies (1310)',
                        'impact_summary' => ($return->payment_status_at_return === 'paid' || ($return->purchaseOrder && $return->purchaseOrder->payment_status === 'paid'))
                            ? 'Fully Paid PO — Approving will create a Supplier Credit Note / Receivable (AR 1200) for ₦' . number_format((float)$return->total_value, 2) . '.'
                            : 'Unpaid/Partial PO — Approving will reduce Accounts Payable liability (AP 2110) by ₦' . number_format((float)$return->total_value, 2) . ' and lower the PO Net Balance Due.',
                    ],
                    'journal_entry' => $return->journalEntry ? [
                        'id' => $return->journalEntry->id,
                        'entry_number' => $return->journalEntry->entry_number ?? 'JE-' . $return->journalEntry->id,
                        'description' => $return->journalEntry->description,
                        'status' => $return->journalEntry->status,
                        'lines' => $return->journalEntry->lines->map(fn ($l) => [
                            'account_name' => $l->account->name ?? 'N/A',
                            'account_code' => $l->account->code ?? '',
                            'debit' => $l->debit_amount,
                            'credit' => $l->credit_amount,
                        ]),
                    ] : null,
                ],
            ]);
        }

        return view('admin.inventory.purchase-orders.return-show', compact('return'));
    }

    /**
     * Approve — observer handles stock deduction + JE.
     */
    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'approval_notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $return = PurchaseOrderReturn::findOrFail($id);

            if ($return->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Only pending returns can be approved'], 422);
            }

            // Stock re-check
            if ($return->batch_id) {
                $batch = StockBatch::findOrFail($return->batch_id);
                if ($batch->current_qty < $return->qty_returned) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient batch quantity. Available: ' . $batch->current_qty,
                    ], 422);
                }
            } else {
                $storeStock = StoreStock::where('product_id', $return->product_id)
                    ->where('store_id', $return->store_id)
                    ->first();
                $available = $storeStock ? $storeStock->current_quantity : 0;
                if ($available < $return->qty_returned) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock. Available: ' . $available,
                    ], 422);
                }
            }

            $return->status = 'approved';
            $return->approved_by = Auth::id();
            $return->approved_at = now();
            $return->approval_notes = $validated['approval_notes'] ?? null;
            $return->save();

            // Observer creates JE + deducts stock + increments returned_qty on item
            DB::commit();

            $return->refresh()->load('journalEntry');

            $message = 'Return approved. Stock deducted.';
            if ($return->journalEntry) {
                $message .= ' JE: ' . ($return->journalEntry->entry_number ?? 'JE-' . $return->journalEntry->id);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'journal_entry_id' => $return->journal_entry_id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PurchaseOrderReturn approve error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject a return.
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:5',
        ]);

        try {
            DB::beginTransaction();

            $return = PurchaseOrderReturn::findOrFail($id);

            if ($return->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Only pending returns can be rejected'], 422);
            }

            $return->status = 'rejected';
            $return->approved_by = Auth::id();
            $return->approved_at = now();
            $return->approval_notes = $validated['rejection_reason'];
            $return->save();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Return rejected']);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DataTables listing.
     */
    public function index()
    {
        $stats = [
            'total_count' => PurchaseOrderReturn::count(),
            'pending_count' => PurchaseOrderReturn::where('status', 'pending')->count(),
            'approved_count' => PurchaseOrderReturn::where('status', 'approved')->count(),
            'total_value' => PurchaseOrderReturn::where('status', 'approved')->sum('total_value'),
        ];

        $stores = \App\Models\Store::active()->orderBy('store_name')->get();

        return view('admin.inventory.purchase-order-returns.index', compact('stats', 'stores'));
    }

    public function datatables(Request $request)
    {
        $query = PurchaseOrderReturn::with([
            'purchaseOrder:id,po_number,supplier_id',
            'purchaseOrder.supplier:id,company_name',
            'purchaseOrderItem:id,packaging_id,packaging_qty',
            'purchaseOrderItem.packaging:id,name,base_unit_qty',
            'product:id,product_name,product_code,base_unit_name',
            'product.packagings:id,product_id,name,base_unit_qty',
            'store:id,store_name',
            'batch:id,batch_number,expiry_date',
            'creator:id,surname,firstname,othername',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            // 1. Transaction & Date
            ->addColumn('return_info', function ($r) {
                $num = '<strong class="text-dark d-block" style="font-size:0.88rem;">' . e($r->return_number ?? ('POR-' . $r->id)) . '</strong>';
                $date = '<small class="text-muted"><i class="mdi mdi-calendar-clock mr-1"></i>' . ($r->created_at ? $r->created_at->format('M d, Y H:i') : '—') . '</small>';

                return $num . $date;
            })
            // 2. Order, Supplier & Store
            ->addColumn('order_info', function ($r) {
                $poLink = '<span class="text-muted">N/A</span>';
                if ($r->purchaseOrder) {
                    $url = route('inventory.purchase-orders.show', $r->purchaseOrder->id);
                    $poLink = '<a href="' . $url . '" class="font-weight-bold text-primary" target="_blank"><i class="mdi mdi-link-variant mr-1"></i>' . e($r->purchaseOrder->po_number) . '</a>';
                }
                $supp = e($r->purchaseOrder->supplier->company_name ?? 'N/A');
                $store = e($r->store->store_name ?? 'N/A');

                return '<div>' . $poLink . '</div><small class="text-muted d-block"><i class="mdi mdi-truck-delivery-outline mr-1"></i>' . $supp . '</small><small class="text-secondary d-block"><i class="mdi mdi-store mr-1"></i>' . $store . '</small>';
            })
            // 3. Product & Batch Details
            ->addColumn('item_details', function ($r) {
                $pName = '<strong class="text-dark">' . e($r->product->product_name ?? 'N/A') . '</strong>';
                if ($r->product && $r->product->product_code) {
                    $pName .= ' <small class="text-muted">(' . e($r->product->product_code) . ')</small>';
                }

                $batchStr = '';
                if ($r->batch) {
                    $batchStr = '<div class="mt-1"><span class="badge badge-light border text-dark" style="font-size:0.75rem;"><i class="mdi mdi-barcode-scan mr-1"></i>' . e($r->batch->batch_number) . '</span>';
                    if ($r->batch->expiry_date) {
                        $expClass = $r->batch->expiry_date->isPast() ? 'badge-danger' : ($r->batch->expiry_date->diffInDays(now()) <= 90 ? 'badge-warning' : 'badge-info');
                        $batchStr .= ' <span class="badge ' . $expClass . '" style="font-size:0.7rem;"><i class="mdi mdi-calendar mr-1"></i>Exp: ' . $r->batch->expiry_date->format('M Y') . '</span>';
                    }
                    $batchStr .= '</div>';
                } else {
                    $batchStr = '<small class="text-muted d-block mt-1">No Batch Info</small>';
                }

                return $pName . $batchStr;
            })
            // 4. Quantity & Packaging
            ->addColumn('qty_packaging', function ($r) {
                $baseUnit = $r->product->base_unit_name ?? 'Units';
                $mainQty = number_format($r->qty_returned) . ' ' . $baseUnit;

                $pkgText = '';
                if ($r->purchaseOrderItem && $r->purchaseOrderItem->packaging) {
                    $pkg = $r->purchaseOrderItem->packaging;
                    if ($pkg->base_unit_qty > 1) {
                        $pkgCount = round($r->qty_returned / $pkg->base_unit_qty, 1);
                        $pkgText = '<small class="text-info font-weight-bold d-block mt-1"><i class="mdi mdi-package-variant-closed mr-1"></i>' . $pkgCount . ' ' . e($pkg->name) . ' (' . (float)$pkg->base_unit_qty . ' ' . $baseUnit . '/pkg)</small>';
                    }
                } elseif ($r->product && $r->product->packagings && $r->product->packagings->count() > 0) {
                    $firstPkg = $r->product->packagings->first();
                    if ($firstPkg && $firstPkg->base_unit_qty > 1) {
                        $pkgCount = round($r->qty_returned / $firstPkg->base_unit_qty, 1);
                        $pkgText = '<small class="text-info font-weight-bold d-block mt-1"><i class="mdi mdi-package-variant-closed mr-1"></i>~' . $pkgCount . ' ' . e($firstPkg->name) . '</small>';
                    }
                }

                return '<div><span class="badge badge-light border font-weight-bold px-2 py-1" style="font-size:0.85rem;">' . $mainQty . '</span></div>' . $pkgText;
            })
            // 5. Value, Reason & Status
            ->addColumn('value_reason_status', function ($r) {
                $val = '<div class="font-weight-bold text-dark mb-1" style="font-size:0.9rem;">₦' . number_format((float)$r->total_value, 2) . '</div>';

                $reasonLabel = ucfirst(str_replace('_', ' ', $r->return_reason ?? 'Other'));
                $reasonBadge = '<span class="badge badge-soft-secondary" style="font-size:0.72rem;">' . e($reasonLabel) . '</span>';
                if ($r->return_notes) {
                    $reasonBadge .= '<small class="text-muted d-block" title="' . e($r->return_notes) . '"><i class="mdi mdi-note-text mr-1"></i>' . e(\Illuminate\Support\Str::limit($r->return_notes, 25)) . '</small>';
                }

                $statusBadges = [
                    'pending' => '<span class="badge badge-warning text-dark px-2 py-1 mt-1 d-inline-block"><i class="mdi mdi-clock-outline mr-1"></i>Pending</span>',
                    'approved' => '<span class="badge badge-success px-2 py-1 mt-1 d-inline-block"><i class="mdi mdi-check-circle mr-1"></i>Approved</span>',
                    'rejected' => '<span class="badge badge-danger px-2 py-1 mt-1 d-inline-block"><i class="mdi mdi-close-circle mr-1"></i>Rejected</span>',
                ];
                $stBadge = $statusBadges[$r->status] ?? ('<span class="badge badge-secondary mt-1">' . e(ucfirst($r->status)) . '</span>');

                return $val . $reasonBadge . '<div class="mt-1">' . $stBadge . '</div>';
            })
            // 6. Recorder & Actions
            ->addColumn('recorder_actions', function ($r) {
                $recorderName = 'N/A';
                if ($r->creator) {
                    $recorderName = trim(($r->creator->surname ?? '') . ' ' . ($r->creator->firstname ?? '') . ' ' . ($r->creator->othername ?? ''));
                }
                $userStr = '<div class="small text-muted mb-2"><i class="mdi mdi-account-outline mr-1"></i>' . e($recorderName ?: 'N/A') . '</div>';

                $btns = '<div class="btn-group btn-group-sm">';
                if ($r->purchase_order_id) {
                    $btns .= '<a href="' . route('inventory.purchase-orders.show', $r->purchase_order_id) . '" class="btn btn-outline-info" title="View Purchase Order"><i class="mdi mdi-eye"></i> View PO</a>';
                }
                if ($r->status === 'pending') {
                    $btns .= '<button class="btn btn-success btn-approve-por" data-id="' . $r->id . '" title="Approve Return"><i class="mdi mdi-check"></i> Approve</button>';
                    $btns .= '<button class="btn btn-danger btn-reject-por" data-id="' . $r->id . '" title="Reject Return"><i class="mdi mdi-close"></i> Reject</button>';
                }
                $btns .= '</div>';

                return $userStr . $btns;
            })
            ->rawColumns(['return_info', 'order_info', 'item_details', 'qty_packaging', 'value_reason_status', 'recorder_actions'])
            ->make(true);
    }

    /**
     * Get receivable batches for a specific PO item (AJAX — used in PO show page).
     */
    public function getBatchesForItem(Request $request)
    {
        $itemId = $request->get('item_id');
        $storeId = $request->get('store_id');

        if (!$itemId || !$storeId) {
            return response()->json([]);
        }

        $item = PurchaseOrderItem::findOrFail($itemId);

        // Find batches in the store for this product that trace back to this PO item
        // First try exact match via reference, otherwise fall back to all product batches in the store
        $batches = StockBatch::where('product_id', $item->product_id)
            ->where('store_id', $storeId)
            ->where('current_qty', '>', 0)
            ->orderBy('received_date', 'asc')
            ->get(['id', 'batch_number', 'expiry_date', 'current_qty', 'cost_price']);

        return response()->json(['batches' => $batches->map(fn ($b) => [
            'id' => $b->id,
            'text' => $b->batch_number . ' — Available: ' . $b->current_qty,
            'batch_number' => $b->batch_number,
            'expiry_date' => $b->expiry_date ? $b->expiry_date->format('Y-m-d') : null,
            'current_qty' => $b->current_qty,
            'unit_cost' => $b->cost_price,
        ])->values()]);
    }

    /**
     * Search for received Purchase Orders (AJAX).
     */
    public function searchPOs(Request $request)
    {
        $q = $request->get('q');
        $storeId = $request->get('store_id');
        $productId = $request->get('product_id');
        $days = $request->get('days', 30);

        $query = PurchaseOrder::query()
            ->whereIn('status', [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED]);

        if ($storeId) {
            $query->where('target_store_id', $storeId);
        }

        $isFallback = false;
        if ($q) {
            $query->where('po_number', 'LIKE', "%{$q}%");
        } elseif ($productId) {
            $query->whereHas('items', function ($q2) use ($productId) {
                $q2->where('product_id', $productId);
            });

            if ((clone $query)->count() === 0) {
                $isFallback = true;
                $query = PurchaseOrder::query()
                    ->whereIn('status', [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED]);
                if ($storeId) {
                    $query->where('target_store_id', $storeId);
                }
            }
        }

        if (!$q && $days !== 'all') {
            $query->where('updated_at', '>=', now()->subDays((int)$days));
        }

        $pos = $query->with('supplier')
            ->withCount('items')
            ->orderBy('updated_at', 'desc')
            ->limit(15)
            ->get();

        return response()->json([
            'is_fallback' => $isFallback,
            'pos' => $pos->map(function ($po) {
                return [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'supplier_name' => $po->supplier->supplier_name ?? 'N/A',
                    'items_count' => $po->items_count,
                    'received_at_label' => $po->received_at ? $po->received_at->format('M d, Y') : $po->updated_at->format('M d, Y'),
                ];
            }),
        ]);
    }

    /**
     * Get items for a specific PO (AJAX).
     */
    public function getPOItems(Request $request)
    {
        $poId = $request->get('purchase_order_id');
        if (!$poId) {
            return response()->json(['items' => []]);
        }

        $items = PurchaseOrderItem::where('purchase_order_id', $poId)
            ->with('product')
            ->get();

        return response()->json(['items' => $items->map(function ($it) {
            // Calculate returnable qty (received - already returned)
            $returned = PurchaseOrderReturn::where('purchase_order_item_id', $it->id)
                ->whereIn('status', ['pending', 'approved'])
                ->sum('qty_returned');

            $returnable = max(0, $it->received_qty - $returned);

            return [
                'id' => $it->id,
                'product_id' => $it->product_id,
                'product_name' => $it->product->product_name ?? 'Unknown',
                'received_qty' => $it->received_qty,
                'returnable_qty' => $returnable,
                'unit_cost' => $it->unit_cost,
            ];
        })]);
    }
}
