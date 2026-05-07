<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrderReturn;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\StoreStock;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PurchaseOrderReturnController extends Controller
{
    /**
     * Store a new PO return record.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id'      => 'required|exists:purchase_orders,id',
            'purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'batch_id'               => 'nullable|exists:stock_batches,id',
            'qty_returned'           => 'required|integer|min:1',
            'unit_cost'              => 'required|numeric|min:0',
            'return_reason'          => 'required|in:wrong_item,damaged,excess,quality_issue,other',
            'return_notes'           => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $po   = PurchaseOrder::findOrFail($validated['purchase_order_id']);
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

            $validated['product_id']              = $item->product_id;
            $validated['store_id']                = $po->target_store_id;
            $validated['total_value']             = $validated['qty_returned'] * $validated['unit_cost'];
            $validated['payment_status_at_return'] = $po->payment_status;
            $validated['created_by']              = Auth::id();
            $validated['status']                  = 'pending';
            $validated['return_number']           = PurchaseOrderReturn::generateReturnNumber();

            $return = PurchaseOrderReturn::create($validated);

            DB::commit();

            return response()->json([
                'success'   => true,
                'message'   => 'Return recorded. Awaiting approval.',
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
                'return'  => [
                    'id'                      => $return->id,
                    'return_number'           => $return->return_number,
                    'po_number'               => $return->purchaseOrder->po_number ?? 'N/A',
                    'supplier'                => $return->purchaseOrder->supplier->supplier_name ?? 'N/A',
                    'product_name'            => $return->product->product_name ?? 'N/A',
                    'store_name'              => $return->store->store_name ?? 'N/A',
                    'batch_number'            => $return->batch->batch_number ?? 'N/A',
                    'qty_returned'            => $return->qty_returned,
                    'unit_cost'               => $return->unit_cost,
                    'total_value'             => $return->total_value,
                    'return_reason'           => $return->return_reason,
                    'return_notes'            => $return->return_notes,
                    'payment_status_at_return'=> $return->payment_status_at_return,
                    'status'                  => $return->status,
                    'expense_adjusted'        => $return->expense_adjusted,
                    'stock_deducted'          => $return->stock_deducted,
                    'created_by'              => $return->creator->name ?? 'N/A',
                    'approved_by'             => $return->approver->name ?? null,
                    'approval_notes'          => $return->approval_notes,
                    'created_at'              => $return->created_at->format('M d, Y h:i A'),
                    'approved_at'             => $return->approved_at ? $return->approved_at->format('M d, Y h:i A') : null,
                    'journal_entry'           => $return->journalEntry ? [
                        'id'           => $return->journalEntry->id,
                        'entry_number' => $return->journalEntry->entry_number ?? 'JE-' . $return->journalEntry->id,
                        'description'  => $return->journalEntry->description,
                        'status'       => $return->journalEntry->status,
                        'lines'        => $return->journalEntry->lines->map(fn ($l) => [
                            'account_name' => $l->account->name ?? 'N/A',
                            'account_code' => $l->account->code ?? '',
                            'debit'        => $l->debit_amount,
                            'credit'       => $l->credit_amount,
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

            $return->status      = 'approved';
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
                'success'          => true,
                'message'          => $message,
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

            $return->status      = 'rejected';
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
        return view('admin.inventory.purchase-order-returns.index');
    }

    public function datatables(Request $request)
    {
        $query = PurchaseOrderReturn::with([
            'purchaseOrder:id,po_number,supplier_id',
            'purchaseOrder.supplier:id,company_name',
            'product:id,product_name',
            'batch:id,batch_number',
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
            ->addColumn('purchase_order', fn ($r) => $r->purchaseOrder->po_number ?? 'N/A')
            ->addColumn('supplier', fn ($r) => $r->purchaseOrder->supplier->company_name ?? 'N/A')
            ->addColumn('product', fn ($r) => $r->product->product_name ?? 'N/A')
            ->addColumn('batch', fn ($r) => $r->batch->batch_number ?? 'N/A')
            ->editColumn('status', function ($r) {
                $badges = [
                    'pending'  => '<span class="status-badge status-pending">Pending</span>',
                    'approved' => '<span class="status-badge status-approved">Approved</span>',
                    'rejected' => '<span class="status-badge status-rejected">Rejected</span>',
                ];
                return $badges[$r->status] ?? $r->status;
            })
            ->addColumn('returned_by', fn ($r) => $r->creator ? ($r->creator->firstname . ' ' . $r->creator->surname) : 'N/A')
            ->addColumn('actions', function ($r) {
                $btns = '<div class="btn-group">';
                if ($r->status === 'pending') {
                    $btns .= '<button class="btn btn-sm btn-success mr-1" onclick="approvePOReturn(' . $r->id . ')"><i class="mdi mdi-check"></i></button>';
                    $btns .= '<button class="btn btn-sm btn-danger" onclick="rejectPOReturn(' . $r->id . ')"><i class="mdi mdi-close"></i></button>';
                }
                $btns .= '</div>';
                return $btns;
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

    /**
     * Get receivable batches for a specific PO item (AJAX — used in PO show page).
     */
    public function getBatchesForItem(Request $request)
    {
        $itemId  = $request->get('item_id');
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
            'id'          => $b->id,
            'text'        => $b->batch_number . ' — Available: ' . $b->current_qty,
            'batch_number' => $b->batch_number,
            'expiry_date' => $b->expiry_date ? $b->expiry_date->format('Y-m-d') : null,
            'current_qty' => $b->current_qty,
            'unit_cost'   => $b->cost_price,
        ])->values()]);
    }

    /**
     * Search for received Purchase Orders (AJAX).
     */
    public function searchPOs(Request $request)
    {
        $q         = $request->get('q');
        $storeId   = $request->get('store_id');
        $productId = $request->get('product_id');
        $days      = $request->get('days', 30);

        $query = PurchaseOrder::query()
            ->whereIn('status', [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED]);

        if ($storeId) {
            $query->where('target_store_id', $storeId);
        }

        $isFallback = false;
        if ($q) {
            $query->where('po_number', 'LIKE', "%{$q}%");
        } elseif ($productId) {
            $query->whereHas('items', function($q2) use ($productId) {
                $q2->where('product_id', $productId);
            });

            if ((clone $query)->count() === 0) {
                $isFallback = true;
                $query = PurchaseOrder::query()
                    ->whereIn('status', [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED]);
                if ($storeId) $query->where('target_store_id', $storeId);
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
                    'id'                 => $po->id,
                    'po_number'          => $po->po_number,
                    'supplier_name'      => $po->supplier->supplier_name ?? 'N/A',
                    'items_count'        => $po->items_count,
                    'received_at_label'  => $po->received_at ? $po->received_at->format('M d, Y') : $po->updated_at->format('M d, Y'),
                ];
            })
        ]);
    }

    /**
     * Get items for a specific PO (AJAX).
     */
    public function getPOItems(Request $request)
    {
        $poId = $request->get('purchase_order_id');
        if (!$poId) return response()->json(['items' => []]);

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
                'id'             => $it->id,
                'product_id'     => $it->product_id,
                'product_name'   => $it->product->product_name ?? 'Unknown',
                'received_qty'   => $it->received_qty,
                'returnable_qty' => $returnable,
                'unit_cost'      => $it->unit_cost,
            ];
        })]);
    }
}
