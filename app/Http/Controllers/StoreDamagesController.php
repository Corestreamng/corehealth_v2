<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StoreDamage;
use App\Models\Product;
use App\Models\StoreStock;
use App\Models\StockBatch;
use App\Models\Store;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StoreDamagesController extends Controller
{
    /**
     * Store a newly created store damage report.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id'     => 'required|exists:products,id',
            'store_id'       => 'required|exists:stores,id',
            'batch_id'       => 'nullable|exists:stock_batches,id',
            'qty_damaged'    => 'required|numeric|min:0.01',
            'unit_cost'      => 'required|numeric|min:0',
            'damage_type'    => 'required|in:expired,broken,contaminated,spoiled,theft,other',
            'damage_reason'  => 'required|string|min:10',
            'discovered_date'=> 'required|date|before_or_equal:today',
        ]);

        try {
            DB::beginTransaction();

            $validated['total_value'] = $validated['qty_damaged'] * $validated['unit_cost'];
            $validated['created_by']  = Auth::id();
            $validated['status']      = 'pending';

            if (isset($validated['batch_id'])) {
                $batch = StockBatch::findOrFail($validated['batch_id']);
                if ($batch->current_qty < $validated['qty_damaged']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient quantity in selected batch. Available: ' . $batch->current_qty,
                    ], 422);
                }
            } else {
                $autoBatch = StockBatch::where('product_id', $validated['product_id'])
                    ->where('store_id', $validated['store_id'])
                    ->where('is_active', true)
                    ->where('current_qty', '>=', $validated['qty_damaged'])
                    ->orderBy('received_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();

                if ($autoBatch) {
                    $validated['batch_id'] = $autoBatch->id;
                } else {
                    $productStock = StoreStock::where('product_id', $validated['product_id'])
                        ->where('store_id', $validated['store_id'])
                        ->first();

                    $available = $productStock ? $productStock->current_quantity : 0;
                    if ($available < $validated['qty_damaged']) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Insufficient stock. Available: ' . $available,
                        ], 422);
                    }
                }
            }

            $damage = StoreDamage::create($validated);

            DB::commit();

            return response()->json([
                'success'   => true,
                'message'   => 'Damage report created. Awaiting approval.',
                'damage_id' => $damage->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StoreDamage store error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create damage report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a damage record (AJAX or full page).
     */
    public function show(Request $request, $id)
    {
        $damage = StoreDamage::with([
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
                'damage'  => [
                    'id'             => $damage->id,
                    'product_name'   => $damage->product->product_name ?? 'N/A',
                    'product_id'     => $damage->product_id,
                    'store_name'     => $damage->store->store_name ?? 'N/A',
                    'store_id'       => $damage->store_id,
                    'batch_number'   => $damage->batch->batch_number ?? 'N/A',
                    'batch_id'       => $damage->batch_id,
                    'qty_damaged'    => $damage->qty_damaged,
                    'unit_cost'      => $damage->unit_cost,
                    'total_value'    => $damage->total_value,
                    'damage_type'    => $damage->damage_type,
                    'damage_reason'  => $damage->damage_reason,
                    'discovered_date'=> $damage->discovered_date ? $damage->discovered_date->format('M d, Y') : 'N/A',
                    'status'         => $damage->status,
                    'stock_deducted' => $damage->stock_deducted,
                    'created_by'     => $damage->creator->name ?? 'N/A',
                    'approved_by'    => $damage->approver->name ?? null,
                    'approval_notes' => $damage->approval_notes,
                    'created_at'     => $damage->created_at->format('M d, Y h:i A'),
                    'approved_at'    => $damage->approved_at ? $damage->approved_at->format('M d, Y h:i A') : null,
                    'journal_entry'  => $damage->journalEntry ? [
                        'id'          => $damage->journalEntry->id,
                        'entry_number'=> $damage->journalEntry->entry_number ?? 'JE-' . $damage->journalEntry->id,
                        'description' => $damage->journalEntry->description,
                        'status'      => $damage->journalEntry->status,
                        'lines'       => $damage->journalEntry->lines->map(fn ($l) => [
                            'account_name' => $l->account->name ?? 'N/A',
                            'account_code' => $l->account->code ?? '',
                            'debit'        => $l->debit_amount,
                            'credit'       => $l->credit_amount,
                            'description'  => $l->description,
                        ]),
                    ] : null,
                ],
            ]);
        }

        return view('admin.inventory.store-workbench.damage-show', compact('damage'));
    }

    /**
     * Approve a damage report — observer creates JE + deducts stock.
     */
    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'approval_notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $damage = StoreDamage::findOrFail($id);

            if ($damage->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending damage reports can be approved',
                ], 422);
            }

            if ($damage->batch_id) {
                $batch = StockBatch::findOrFail($damage->batch_id);
                if ($batch->current_qty < $damage->qty_damaged) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient batch quantity at approval time. Available: ' . $batch->current_qty,
                    ], 422);
                }
            } else {
                $productStock = StoreStock::where('product_id', $damage->product_id)
                    ->where('store_id', $damage->store_id)
                    ->first();

                $available = $productStock ? $productStock->current_quantity : 0;
                if ($available < $damage->qty_damaged) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock at approval time. Available: ' . $available,
                    ], 422);
                }
            }

            $damage->status      = 'approved';
            $damage->approved_by = Auth::id();
            $damage->approved_at = now();
            $damage->approval_notes = $validated['approval_notes'] ?? null;
            $damage->save();

            // Observer creates JE + deducts stock
            DB::commit();

            $damage->refresh()->load('journalEntry');

            $message = 'Damage approved. Stock deducted and journal entry created.';
            if ($damage->journalEntry) {
                $message .= ' JE: ' . ($damage->journalEntry->entry_number ?? 'JE-' . $damage->journalEntry->id);
            }

            return response()->json([
                'success'          => true,
                'message'          => $message,
                'journal_entry_id' => $damage->journal_entry_id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StoreDamage approve error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a damage report.
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10',
        ]);

        try {
            DB::beginTransaction();

            $damage = StoreDamage::findOrFail($id);

            if ($damage->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending damage reports can be rejected',
                ], 422);
            }

            $damage->status      = 'rejected';
            $damage->approved_by = Auth::id();
            $damage->approved_at = now();
            $damage->approval_notes = $validated['rejection_reason'];
            $damage->save();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Damage report rejected']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DataTables endpoint.
     */
    public function index()
    {
        return view('admin.inventory.store-damages.index');
    }

    public function datatables(Request $request)
    {
        $query = StoreDamage::with([
            'product:id,product_name',
            'store:id,store_name',
            'batch:id,batch_number',
            'creator:id,surname,firstname,othername',
            'approver:id,surname,firstname,othername',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('damage_type')) {
            $query->where('damage_type', $request->damage_type);
        }
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('date_from')) {
            $query->where('discovered_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('discovered_date', '<=', $request->date_to);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('product', fn ($d) => $d->product->product_name ?? 'N/A')
            ->addColumn('batch', fn ($d) => $d->batch->batch_number ?? 'N/A')
            ->addColumn('store', fn ($d) => $d->store->store_name ?? 'N/A')
            ->editColumn('status', function ($d) {
                $badges = [
                    'pending'  => '<span class="status-badge status-pending">Pending</span>',
                    'approved' => '<span class="status-badge status-approved">Approved</span>',
                    'rejected' => '<span class="status-badge status-rejected">Rejected</span>',
                ];
                return $badges[$d->status] ?? $d->status;
            })
            ->addColumn('recorded_by', fn ($d) => $d->recorder ? ($d->recorder->firstname . ' ' . $d->recorder->surname) : 'N/A')
            ->addColumn('actions', function ($d) {
                $btns = '<div class="btn-group">';
                if ($d->status === 'pending') {
                    $btns .= '<button class="btn btn-sm btn-success mr-1" onclick="approveDamage(' . $d->id . ')"><i class="mdi mdi-check"></i></button>';
                    $btns .= '<button class="btn btn-sm btn-danger" onclick="rejectDamage(' . $d->id . ')"><i class="mdi mdi-close"></i></button>';
                }
                $btns .= '</div>';
                return $btns;
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

    /**
     * Search products with stock in a store (AJAX).
     */
    public function searchProducts(Request $request)
    {
        $storeId = $request->get('store_id');
        if (!$storeId) {
            return response()->json([]);
        }

        $search = $request->get('search', '');

        $products = Product::select(
                'products.id',
                'products.product_name',
                'products.product_code',
                'store_stocks.current_quantity',
                'prices.pr_buy_price as unit_cost'
            )
            ->join('store_stocks', 'products.id', '=', 'store_stocks.product_id')
            ->leftJoin('prices', 'products.id', '=', 'prices.product_id')
            ->where('store_stocks.store_id', $storeId)
            ->where('store_stocks.current_quantity', '>', 0)
            ->where(function ($q) use ($search) {
                $q->where('products.product_name', 'LIKE', "%{$search}%")
                  ->orWhere('products.product_code', 'LIKE', "%{$search}%");
            })
            ->orderBy('products.product_name')
            ->limit(50)
            ->get();

        return response()->json(['products' => $products->map(fn ($p) => [
            'id'               => $p->id,
            'text'             => $p->product_name . ($p->product_code ? ' (' . $p->product_code . ')' : '') . ' — Stock: ' . $p->current_quantity,
            'product_name'     => $p->product_name,
            'current_quantity' => $p->current_quantity,
            'unit_cost'        => $p->unit_cost,
        ])->values()]);
    }

    /**
     * Get available batches for a product in a store (AJAX).
     */
    public function getBatches(Request $request)
    {
        $productId = $request->get('product_id');
        $storeId   = $request->get('store_id');

        if (!$productId || !$storeId) {
            return response()->json([]);
        }

        $batches = StockBatch::with('product:id,product_name,base_unit_name')->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->where('current_qty', '>', 0)
            ->orderBy('expiry_date')
            ->get(['id', 'product_id', 'batch_number', 'expiry_date', 'current_qty', 'cost_price']);

        return response()->json(['batches' => $batches->map(fn ($b) => [
            'id'           => $b->id,
            'product_id'   => $b->product_id,
            'product_name' => $b->product->product_name ?? 'Unknown',
            'text'         => $b->batch_number . ' (Exp: ' . ($b->expiry_date ?? 'N/A') . ') — Available: ' . $b->current_qty,
            'batch_number' => $b->batch_number,
            'expiry_date'  => $b->expiry_date ? $b->expiry_date->format('Y-m-d') : null,
            'current_qty'  => $b->current_qty,
            'unit_cost'    => $b->cost_price,
            'base_unit_name' => $b->product->base_unit_name ?? 'Piece',
        ])->values()]);
    }

    /**
     * Get recent batches with stock for the damage modal (AJAX).
     */
    public function getRecentBatches(Request $request)
    {
        $storeId = $request->get('store_id');
        $status  = $request->get('status', 'recent'); // recent, near-expiry, low-stock, all

        if (!$storeId) {
            return response()->json(['batches' => []]);
        }

        $query = StockBatch::with('product:id,product_name,product_code,base_unit_name')
            ->where('store_id', $storeId)
            ->where('current_qty', '>', 0);

        if ($status === 'near-expiry') {
            $query->whereNotNull('expiry_date')
                ->where('expiry_date', '<=', now()->addDays(90))
                ->orderBy('expiry_date', 'asc');
        } elseif ($status === 'low-stock') {
            // Assume low stock is < 10 for now
            $query->where('current_qty', '<', 10)
                ->orderBy('current_qty', 'asc');
        } else {
            $query->orderBy('received_date', 'desc')
                ->orderBy('id', 'desc');
        }

        $batches = $query->limit(15)->get();

        return response()->json(['batches' => $batches->map(fn ($b) => [
            'id'           => $b->id,
            'product_id'   => $b->product_id,
            'product_name' => $b->product->product_name ?? 'Unknown',
            'batch_number' => $b->batch_number,
            'expiry_date'  => $b->expiry_date ? $b->expiry_date->format('Y-m-d') : null,
            'current_qty'  => $b->current_qty,
            'unit_cost'    => $b->cost_price,
            'base_unit_name' => $b->product->base_unit_name ?? 'Piece',
        ])->values()]);
    }
}
