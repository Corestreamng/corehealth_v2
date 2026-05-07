<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StoreRequisitionReturn;
use App\Models\StoreRequisition;
use App\Models\StoreRequisitionItem;
use App\Models\StockBatch;
use App\Models\StoreStock;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StoreRequisitionReturnController extends Controller
{
    /**
     * Store a new requisition return record.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_requisition_id'      => 'required|exists:store_requisitions,id',
            'store_requisition_item_id' => 'required|exists:store_requisition_items,id',
            'batch_id'                  => 'nullable|exists:stock_batches,id',
            'qty_returned'              => 'required|integer|min:1',
            'return_condition'          => 'required|in:good,damaged,partial',
            'return_reason'             => 'required|string|min:5',
            'restock'                   => 'required|boolean',
        ]);

        try {
            DB::beginTransaction();

            $requisition = StoreRequisition::findOrFail($validated['store_requisition_id']);
            $item        = StoreRequisitionItem::findOrFail($validated['store_requisition_item_id']);

            if ($item->store_requisition_id !== $requisition->id) {
                return response()->json(['success' => false, 'message' => 'Item does not belong to this requisition'], 422);
            }

            // Returns go from destination back to source
            $validated['source_store_id']      = $requisition->to_store_id;
            $validated['destination_store_id'] = $requisition->from_store_id;
            $validated['product_id']           = $item->product_id;
            $validated['created_by']           = Auth::id();
            $validated['status']               = 'pending';

            // Check qty returned doesn't exceed fulfilled qty
            $alreadyReturned = StoreRequisitionReturn::where('store_requisition_item_id', $item->id)
                ->whereIn('status', ['pending', 'approved'])
                ->sum('qty_returned');
            $maxReturnable = ($item->fulfilled_qty ?? $item->approved_qty ?? $item->requested_qty) - $alreadyReturned;

            if ($validated['qty_returned'] > $maxReturnable) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot return {$validated['qty_returned']}. Max returnable: {$maxReturnable}",
                ], 422);
            }

            // If a batch_id is given, verify it belongs to the source store
            if (!empty($validated['batch_id'])) {
                $batch = StockBatch::findOrFail($validated['batch_id']);
                if ($batch->store_id !== $validated['source_store_id']) {
                    return response()->json(['success' => false, 'message' => 'Batch does not belong to the returning store'], 422);
                }
                if ($batch->current_qty < $validated['qty_returned']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Batch has only ' . $batch->current_qty . ' available',
                    ], 422);
                }
            }

            $return = StoreRequisitionReturn::create($validated);

            DB::commit();

            return response()->json([
                'success'   => true,
                'message'   => 'Return recorded. Awaiting approval.',
                'return_id' => $return->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StoreRequisitionReturn store error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show a return record (AJAX or full page).
     */
    public function show(Request $request, $id)
    {
        $return = StoreRequisitionReturn::with([
            'requisition',
            'requisitionItem.product',
            'product',
            'sourceStore',
            'destinationStore',
            'batch',
            'creator',
            'approver',
        ])->findOrFail($id);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'return'  => [
                    'id'                => $return->id,
                    'requisition_id'    => $return->store_requisition_id,
                    'product_name'      => $return->product->product_name ?? 'N/A',
                    'source_store'      => $return->sourceStore->store_name ?? 'N/A',
                    'destination_store' => $return->destinationStore->store_name ?? 'N/A',
                    'batch_number'      => $return->batch->batch_number ?? 'N/A',
                    'qty_returned'      => $return->qty_returned,
                    'return_condition'  => $return->return_condition,
                    'return_reason'     => $return->return_reason,
                    'restock'           => $return->restock,
                    'status'            => $return->status,
                    'stock_adjusted'    => $return->stock_adjusted,
                    'created_by'        => $return->creator->name ?? 'N/A',
                    'approved_by'       => $return->approver->name ?? null,
                    'approval_notes'    => $return->approval_notes,
                    'created_at'        => $return->created_at->format('M d, Y h:i A'),
                    'approved_at'       => $return->approved_at ? $return->approved_at->format('M d, Y h:i A') : null,
                ],
            ]);
        }

        return view('admin.inventory.store-workbench.req-return-show', compact('return'));
    }

    /**
     * Approve — observer handles stock movement.
     */
    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'approval_notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $return = StoreRequisitionReturn::findOrFail($id);

            if ($return->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Only pending returns can be approved'], 422);
            }

            // Stock check at source (returning) store
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
                    ->where('store_id', $return->source_store_id)
                    ->first();

                $available = $storeStock ? $storeStock->current_quantity : 0;
                if ($available < $return->qty_returned) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock at returning store. Available: ' . $available,
                    ], 422);
                }
            }

            $return->status      = 'approved';
            $return->approved_by = Auth::id();
            $return->approved_at = now();
            $return->approval_notes = $validated['approval_notes'] ?? null;
            $return->save();

            // Observer handles stock deduction + re-add
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Return approved. Stock adjusted.']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StoreRequisitionReturn approve error: ' . $e->getMessage());

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

            $return = StoreRequisitionReturn::findOrFail($id);

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
     * DataTables for the pending panel and listing.
     */
    public function index()
    {
        return view('admin.inventory.requisition-returns.index');
    }

    public function datatables(Request $request)
    {
        $query = StoreRequisitionReturn::with([
            'requisition:id,requisition_number',
            'product:id,product_name',
            'batch:id,batch_number',
            'sourceStore:id,store_name',
            'creator:id,surname,firstname,othername',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('store_id')) {
            $query->where('source_store_id', $request->store_id);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('requisition', fn ($r) => $r->requisition->requisition_number ?? 'N/A')
            ->addColumn('product', fn ($r) => $r->product->product_name ?? 'N/A')
            ->addColumn('batch', fn ($r) => $r->batch->batch_number ?? 'N/A')
            ->addColumn('store', fn ($r) => $r->sourceStore->store_name ?? 'N/A')
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
                    $btns .= '<button class="btn btn-sm btn-success mr-1" onclick="approveReturn(' . $r->id . ')"><i class="mdi mdi-check"></i></button>';
                    $btns .= '<button class="btn btn-sm btn-danger" onclick="rejectReturn(' . $r->id . ')"><i class="mdi mdi-close"></i></button>';
                }
                $btns .= '</div>';
                return $btns;
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

    /**
     * Get returnable items for a requisition (AJAX — populates return form).
     */
    public function getRequisitionItems(Request $request)
    {
        $requisitionId = $request->get('requisition_id');
        if (!$requisitionId) {
            return response()->json([]);
        }

        $requisition = StoreRequisition::with(['items.product'])->findOrFail($requisitionId);

        $items = $requisition->items->filter(function ($item) {
            $fulfilled = $item->fulfilled_qty ?? $item->approved_qty ?? 0;
            if ($fulfilled <= 0) return false;

            $alreadyReturned = StoreRequisitionReturn::where('store_requisition_item_id', $item->id)
                ->whereIn('status', ['pending', 'approved'])
                ->sum('qty_returned');

            return ($fulfilled - $alreadyReturned) > 0;
        })->values();

        return response()->json(['success' => true, 'items' => $items->map(function ($item) {
            $fulfilled      = $item->fulfilled_qty ?? $item->approved_qty ?? 0;
            $alreadyReturned = StoreRequisitionReturn::where('store_requisition_item_id', $item->id)
                ->whereIn('status', ['pending', 'approved'])
                ->sum('qty_returned');
            $maxReturnable = $fulfilled - $alreadyReturned;

            return [
                'id'             => $item->id,
                'product_id'     => $item->product_id,
                'product_name'   => $item->product->product_name ?? 'N/A',
                'fulfilled_qty'  => $fulfilled,
                'already_returned' => $alreadyReturned,
                'returnable_qty' => $maxReturnable,
            ];
        })->values()]);
    }

    /**
     * Get batches at the returning (destination) store for a given product (AJAX).
     */
    public function getBatchesForProduct(Request $request)
    {
        $productId = $request->get('product_id');
        $storeId   = $request->get('store_id'); // This is to_store_id of the requisition

        if (!$productId || !$storeId) {
            return response()->json([]);
        }

        $batches = StockBatch::where('product_id', $productId)
            ->where('store_id', $storeId)
            ->where('current_qty', '>', 0)
            ->orderBy('received_date', 'asc')
            ->get(['id', 'batch_number', 'expiry_date', 'current_qty']);

        return response()->json(['batches' => $batches->map(fn ($b) => [
            'id'          => $b->id,
            'text'        => $b->batch_number . ' — Available: ' . $b->current_qty,
            'batch_number' => $b->batch_number,
            'current_qty' => $b->current_qty,
        ])->values()]);
    }

    /**
     * Search requisitions received by a store (AJAX).
     */
    public function searchRequisitions(Request $request)
    {
        $q           = $request->get('q');
        $storeId     = $request->get('store_id');
        $productId   = $request->get('product_id');
        $involvement = $request->get('involvement', 'received');
        $status      = $request->get('status', 'all');
        $days        = $request->get('days', 30);

        $query = StoreRequisition::query();

        if ($storeId) {
            $query->where(function ($q2) use ($storeId) {
                $q2->where('to_store_id', $storeId)
                   ->orWhere('from_store_id', $storeId);
            });
        }

        $isFallback = false;
        if ($q) {
            $query->where('requisition_number', 'LIKE', "%{$q}%");
        } elseif ($productId) {
            $query->whereHas('items', function($q3) use ($productId) {
                $q3->where('product_id', $productId);
            });
            
            // Check if results exist for this product
            if ((clone $query)->count() === 0) {
                $isFallback = true;
                // Re-initialize query for fallback (recent for store)
                $query = StoreRequisition::query();
                if ($storeId) {
                    $query->where(function ($q2) use ($storeId) {
                        $q2->where('to_store_id', $storeId)->orWhere('from_store_id', $storeId);
                    });
                }
            }
        }

        if (!$q) {
            if ($storeId && !$isFallback) {
                if ($involvement === 'received') {
                    $query->where('to_store_id', $storeId);
                } elseif ($involvement === 'sent') {
                    $query->where('from_store_id', $storeId);
                }
            }

            if ($status === 'fulfilled') {
                $query->where('status', 'fulfilled');
            } elseif ($status === 'partial') {
                $query->where('status', 'partial');
            } else {
                $query->whereIn('status', ['fulfilled', 'partial', 'approved']);
            }

            if ($days !== 'all') {
                $query->where('updated_at', '>=', now()->subDays((int)$days));
            }
        }

        $requisitions = $query->with(['fromStore', 'toStore'])
            ->withCount('items')
            ->orderBy('updated_at', 'desc')
            ->limit(15)
            ->get();

        return response()->json([
            'is_fallback' => $isFallback,
            'requisitions' => $requisitions->map(function ($r) {
                return [
                    'id'                 => $r->id,
                    'requisition_number' => $r->requisition_number,
                    'from_store_name'    => $r->fromStore->store_name ?? 'Unknown',
                    'to_store_name'      => $r->toStore->store_name ?? 'Unknown',
                    'from_store_id'      => $r->from_store_id,
                    'to_store_id'        => $r->to_store_id,
                    'items_count'        => $r->items_count,
                    'status'             => ucfirst($r->status),
                    'fulfilled_at_label' => $r->fulfilled_at ? $r->fulfilled_at->format('M d, Y') : ($r->approved_at ? $r->approved_at->format('M d, Y') : 'N/A'),
                ];
            })
        ]);
    }
}
