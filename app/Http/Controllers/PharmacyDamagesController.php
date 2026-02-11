<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PharmacyDamage;
use App\Models\Product;
use App\Models\StoreStock;
use App\Models\StockBatch;
use App\Models\Store;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PharmacyDamagesController extends Controller
{
    /**
     * Display a listing of damage reports or return stats for AJAX
     */
    public function index(Request $request)
    {
        if ($request->ajax() && $request->has('stats_only')) {
            $total = PharmacyDamage::count();
            $pending = PharmacyDamage::where('status', 'pending')->count();
            $approved = PharmacyDamage::where('status', 'approved')->count();
            $rejected = PharmacyDamage::where('status', 'rejected')->count();
            $totalValue = PharmacyDamage::where('status', 'approved')->sum('total_value');
            $stockDeducted = PharmacyDamage::where('stock_deducted', true)->count();

            return response()->json([
                'stats' => [
                    'total' => $total,
                    'pending' => $pending,
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'total_value' => $totalValue,
                    'stock_deducted' => $stockDeducted,
                ]
            ]);
        }

        return view('admin.pharmacy.damages.index');
    }

    /**
     * Show the form for creating a new damage report
     */
    public function create()
    {
        $stores = Store::where('store_type', 'pharmacy')
            ->orderBy('store_name')
            ->get();

        return view('admin.pharmacy.damages.create', compact('stores'));
    }

    /**
     * Store a newly created damage report
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'store_id' => 'required|exists:stores,id',
            'batch_id' => 'nullable|exists:stock_batches,id',
            'qty_damaged' => 'required|numeric|min:0.01',
            'unit_cost' => 'required|numeric|min:0',
            'damage_type' => 'required|in:expired,broken,contaminated,spoiled,theft,other',
            'damage_reason' => 'required|string|min:10',
            'discovered_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            // Calculate total value
            $validated['total_value'] = $validated['qty_damaged'] * $validated['unit_cost'];
            $validated['created_by'] = Auth::id();
            $validated['status'] = 'pending';

            // Check if batch has sufficient quantity
            if (isset($validated['batch_id'])) {
                $batch = StockBatch::findOrFail($validated['batch_id']);
                if ($batch->current_qty < $validated['qty_damaged']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient quantity in selected batch. Available: ' . $batch->current_qty
                    ], 422);
                }
            } else {
                // Auto-assign the first available batch (FIFO) when no batch selected
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
                    // No single batch can cover it — check total store stock
                    $productStock = StoreStock::where('product_id', $validated['product_id'])
                        ->where('store_id', $validated['store_id'])
                        ->first();

                    if (!$productStock || $productStock->current_quantity < $validated['qty_damaged']) {
                        $available = $productStock ? $productStock->current_quantity : 0;
                        return response()->json([
                            'success' => false,
                            'message' => 'Insufficient stock. Available: ' . $available
                        ], 422);
                    }
                }
            }

            $damage = PharmacyDamage::create($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Damage report created successfully. Awaiting approval.',
                'damage_id' => $damage->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating damage report: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create damage report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified damage report
     */
    public function show(Request $request, $id)
    {
        $damage = PharmacyDamage::with([
            'product',
            'store',
            'batch',
            'creator',
            'approver',
            'journalEntry.lines.account'
        ])->findOrFail($id);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'damage' => [
                    'id' => $damage->id,
                    'product_name' => $damage->product->product_name ?? 'N/A',
                    'product_id' => $damage->product_id,
                    'store_name' => $damage->store->store_name ?? 'N/A',
                    'store_id' => $damage->store_id,
                    'batch_number' => $damage->batch->batch_number ?? 'N/A',
                    'batch_id' => $damage->batch_id,
                    'qty_damaged' => $damage->qty_damaged,
                    'unit_cost' => $damage->unit_cost,
                    'total_value' => $damage->total_value,
                    'damage_type' => $damage->damage_type,
                    'damage_reason' => $damage->damage_reason,
                    'discovered_date' => $damage->discovered_date ? $damage->discovered_date->format('M d, Y') : 'N/A',
                    'status' => $damage->status,
                    'stock_deducted' => $damage->stock_deducted,
                    'created_by' => $damage->creator->name ?? 'N/A',
                    'approved_by' => $damage->approver->name ?? null,
                    'approval_notes' => $damage->approval_notes,
                    'created_at' => $damage->created_at->format('M d, Y h:i A'),
                    'approved_at' => $damage->approved_at ? $damage->approved_at->format('M d, Y h:i A') : null,
                    'journal_entry' => $damage->journalEntry ? [
                        'id' => $damage->journalEntry->id,
                        'reference' => $damage->journalEntry->reference ?? 'JE-' . $damage->journalEntry->id,
                        'description' => $damage->journalEntry->description,
                        'status' => $damage->journalEntry->status ?? null,
                        'lines' => $damage->journalEntry->lines->map(function($line) {
                            return [
                                'account_name' => $line->account->name ?? 'N/A',
                                'account_code' => $line->account->code ?? '',
                                'debit' => $line->debit_amount,
                                'credit' => $line->credit_amount,
                                'description' => $line->description,
                            ];
                        })
                    ] : null,
                ]
            ]);
        }

        return view('admin.pharmacy.damages.show', compact('damage'));
    }

    /**
     * Approve a damage report (triggers JE creation via observer)
     */
    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'approval_notes' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            $damage = PharmacyDamage::findOrFail($id);

            if ($damage->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending damage reports can be approved'
                ], 422);
            }

            // Check stock availability again at approval time
            if ($damage->batch_id) {
                $batch = StockBatch::findOrFail($damage->batch_id);
                if ($batch->current_qty < $damage->qty_damaged) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient quantity in batch at approval time. Available: ' . $batch->current_qty
                    ], 422);
                }
            } else {
                $productStock = StoreStock::where('product_id', $damage->product_id)
                    ->where('store_id', $damage->store_id)
                    ->first();

                if (!$productStock || $productStock->current_quantity < $damage->qty_damaged) {
                    $available = $productStock ? $productStock->current_quantity : 0;
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock at approval time. Available: ' . $available
                    ], 422);
                }
            }

            $damage->status = 'approved';
            $damage->approved_by = Auth::id();
            $damage->approved_at = now();
            $damage->approval_notes = $validated['approval_notes'] ?? null;
            $damage->save();

            // Observer will create JE and deduct stock

            DB::commit();

            // Reload to get JE created by observer
            $damage->refresh();
            $damage->load('journalEntry');

            $message = 'Damage report approved. Stock deducted and journal entry created.';
            if ($damage->journalEntry) {
                $message .= ' JE Ref: ' . ($damage->journalEntry->reference ?? 'JE-' . $damage->journalEntry->id);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'journal_entry_id' => $damage->journal_entry_id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approving damage report: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve damage report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a damage report
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10'
        ]);

        try {
            DB::beginTransaction();

            $damage = PharmacyDamage::findOrFail($id);

            if ($damage->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending damage reports can be rejected'
                ], 422);
            }

            $damage->status = 'rejected';
            $damage->approved_by = Auth::id();
            $damage->approved_at = now();
            $damage->approval_notes = $validated['rejection_reason'];
            $damage->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Damage report rejected'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error rejecting damage report: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject damage report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DataTables endpoint for damage reports listing
     */
    public function datatables(Request $request)
    {
        $query = PharmacyDamage::with([
            'product:id,product_name',
            'store:id,store_name',
            'batch:id,batch_number',
            'creator:id,surname,firstname,othername',
            'approver:id,surname,firstname,othername'
        ]);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by damage type
        if ($request->filled('damage_type')) {
            $query->where('damage_type', $request->damage_type);
        }

        // Filter by store
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('discovered_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('discovered_date', '<=', $request->date_to);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('product_name', function ($damage) {
                return $damage->product ? $damage->product->product_name : 'N/A';
            })
            ->addColumn('store_name', function ($damage) {
                return $damage->store ? $damage->store->store_name : 'N/A';
            })
            ->addColumn('batch_number', function ($damage) {
                return $damage->batch ? $damage->batch->batch_number : 'No Batch';
            })
            ->addColumn('item_info', function ($damage) {
                $product = $damage->product ? e($damage->product->product_name) : 'N/A';
                $store = $damage->store ? e($damage->store->store_name) : 'N/A';
                $batch = $damage->batch ? '<span class="text-info" title="Batch">' . e($damage->batch->batch_number) . '</span>' : '';
                return '<strong>' . $product . '</strong>'
                    . '<br><small class="text-muted"><i class="mdi mdi-store"></i> ' . $store . '</small>'
                    . ($batch ? ' <small>' . $batch . '</small>' : '');
            })
            ->addColumn('details_info', function ($damage) {
                $date = $damage->discovered_date ? $damage->discovered_date->format('M d, Y') : '-';
                return '<span class="font-weight-bold">' . $damage->qty_damaged . '</span> × ₦' . number_format($damage->unit_cost, 2)
                    . '<br><small class="text-muted">' . $date . '</small>';
            })
            ->addColumn('status_info', function ($damage) {
                $typeBadges = [
                    'expired' => '<span class="badge badge-warning">Expired</span>',
                    'broken' => '<span class="badge badge-danger">Broken</span>',
                    'contaminated' => '<span class="badge badge-danger">Contam.</span>',
                    'spoiled' => '<span class="badge badge-info">Spoiled</span>',
                    'theft' => '<span class="badge badge-dark">Theft</span>',
                    'other' => '<span class="badge badge-secondary">Other</span>',
                ];
                $statusBadges = [
                    'pending' => '<span class="badge badge-warning">Pending</span>',
                    'approved' => '<span class="badge badge-success">Approved</span>',
                    'rejected' => '<span class="badge badge-danger">Rejected</span>',
                ];
                $type = $typeBadges[$damage->damage_type] ?? $damage->damage_type;
                $status = $statusBadges[$damage->status] ?? $damage->status;
                $stock = $damage->stock_deducted
                    ? '<span class="badge badge-success badge-sm"><i class="fa fa-check"></i></span>'
                    : '<span class="badge badge-secondary badge-sm">—</span>';
                return $type . ' ' . $status . ' ' . $stock;
            })
            ->addColumn('damage_type_badge', function ($damage) {
                $badges = [
                    'expired' => '<span class="badge badge-warning">Expired</span>',
                    'broken' => '<span class="badge badge-danger">Broken</span>',
                    'contaminated' => '<span class="badge badge-danger">Contaminated</span>',
                    'spoiled' => '<span class="badge badge-info">Spoiled</span>',
                    'theft' => '<span class="badge badge-dark">Theft</span>',
                    'other' => '<span class="badge badge-secondary">Other</span>',
                ];
                return $badges[$damage->damage_type] ?? $damage->damage_type;
            })
            ->addColumn('status_badge', function ($damage) {
                $badges = [
                    'pending' => '<span class="badge badge-warning">Pending</span>',
                    'approved' => '<span class="badge badge-success">Approved</span>',
                    'rejected' => '<span class="badge badge-danger">Rejected</span>',
                ];
                return $badges[$damage->status] ?? $damage->status;
            })
            ->addColumn('created_by_name', function ($damage) {
                return $damage->creator ? $damage->creator->name : 'N/A';
            })
            ->addColumn('approved_by_name', function ($damage) {
                return $damage->approver ? $damage->approver->name : '-';
            })
            ->addColumn('stock_deducted_badge', function ($damage) {
                if ($damage->stock_deducted) {
                    return '<span class="badge badge-success"><i class="fa fa-check"></i> Deducted</span>';
                }
                return '<span class="badge badge-secondary">Not Deducted</span>';
            })
            ->addColumn('actions', function ($damage) {
                $actions = '<div class="btn-group">';
                $actions .= '<button class="btn btn-sm btn-info btn-view-damage" data-id="' . $damage->id . '" title="View Details"><i class="fa fa-eye"></i></button>';

                if ($damage->status === 'pending') {
                    $actions .= '<button class="btn btn-sm btn-success approve-damage" data-id="' . $damage->id . '" title="Approve"><i class="fa fa-check"></i></button>';
                    $actions .= '<button class="btn btn-sm btn-danger reject-damage" data-id="' . $damage->id . '" title="Reject"><i class="fa fa-times"></i></button>';
                }

                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['damage_type_badge', 'status_badge', 'stock_deducted_badge', 'item_info', 'details_info', 'status_info', 'actions'])
            ->make(true);
    }

    /**
     * Search products with current stock (AJAX endpoint)
     */
    public function searchProducts(Request $request)
    {
        $search = $request->get('search', '');
        $storeId = $request->get('store_id');

        if (!$storeId) {
            return response()->json([]);
        }

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
            ->where(function($q) use ($search) {
                $q->where('products.product_name', 'LIKE', "%{$search}%")
                  ->orWhere('products.product_code', 'LIKE', "%{$search}%");
            })
            ->orderBy('products.product_name')
            ->limit(50)
            ->get();

        return response()->json($products->map(function($product) {
            return [
                'id' => $product->id,
                'text' => $product->product_name . ($product->product_code ? ' (' . $product->product_code . ')' : '') . ' - Stock: ' . $product->current_quantity,
                'product_name' => $product->product_name,
                'product_code' => $product->product_code,
                'current_quantity' => $product->current_quantity,
                'unit_cost' => $product->unit_cost
            ];
        }));
    }

    /**
     * Get available batches for a product in a store (AJAX endpoint)
     */
    public function getBatches(Request $request)
    {
        $productId = $request->get('product_id');
        $storeId = $request->get('store_id');

        if (!$productId || !$storeId) {
            return response()->json([]);
        }

        $batches = StockBatch::where('product_id', $productId)
            ->where('store_id', $storeId)
            ->where('current_qty', '>', 0)
            ->orderBy('expiry_date')
            ->get(['id', 'batch_number', 'expiry_date', 'current_qty', 'cost_price']);

        return response()->json($batches->map(function($batch) {
            return [
                'id' => $batch->id,
                'text' => $batch->batch_number . ' (Exp: ' . $batch->expiry_date . ') - Available: ' . $batch->current_qty,
                'batch_number' => $batch->batch_number,
                'expiry_date' => $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : null,
                'quantity_available' => $batch->current_qty,
                'unit_cost' => $batch->cost_price
            ];
        }));
    }
}
