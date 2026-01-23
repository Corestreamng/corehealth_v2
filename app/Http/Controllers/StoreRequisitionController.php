<?php

namespace App\Http\Controllers;

use App\Models\StoreRequisition;
use App\Models\StoreRequisitionItem;
use App\Models\Product;
use App\Models\Store;
use App\Services\RequisitionService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Controller: StoreRequisitionController
 *
 * Plan Reference: Phase 4 - Controllers
 * Purpose: Handle inter-store requisition workflow
 *
 * Permissions Required:
 * - requisitions.view: View requisitions
 * - requisitions.create: Create new requisitions
 * - requisitions.approve: Approve/reject requisitions
 * - requisitions.fulfill: Fulfill requisitions
 *
 * Related Models: StoreRequisition, StoreRequisitionItem, Product, Store
 * Related Files:
 * - app/Services/RequisitionService.php
 * - resources/views/inventory/requisitions/
 */
class StoreRequisitionController extends Controller
{
    protected RequisitionService $requisitionService;
    protected StockService $stockService;

    public function __construct(RequisitionService $requisitionService, StockService $stockService)
    {
        $this->requisitionService = $requisitionService;
        $this->stockService = $stockService;
    }

    /**
     * Display a listing of requisitions
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $user = auth()->user();
            $query = StoreRequisition::with(['fromStore', 'toStore', 'requester', 'items'])
                ->orderBy('created_at', 'desc');

            // Apply user-based access filter unless admin/superadmin/store role
            if (!$user->hasAnyRole(['admin', 'super-admin', 'store', 'Store'])) {
                // Regular users can only see requisitions they are involved with
                $query->where(function ($q) use ($user) {
                    $q->where('requested_by', $user->id)
                      ->orWhere('approved_by', $user->id)
                      ->orWhere('rejected_by', $user->id)
                      ->orWhere('fulfilled_by', $user->id);
                });
            }

            // Apply queue filters
            if ($request->queue === 'pending-approval') {
                $query->where('status', StoreRequisition::STATUS_PENDING);
            } elseif ($request->queue === 'pending-fulfillment') {
                $query->whereIn('status', [StoreRequisition::STATUS_APPROVED, StoreRequisition::STATUS_PARTIAL]);
            } elseif ($request->queue === 'my-requisitions') {
                $query->where('requested_by', $user->id);
            }

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('from_store_id')) {
                $query->where('from_store_id', $request->from_store_id);
            }
            if ($request->filled('to_store_id')) {
                $query->where('to_store_id', $request->to_store_id);
            }

            return DataTables::of($query)
                ->addColumn('request_date', fn($r) => $r->created_at->format('d M Y H:i'))
                ->addColumn('from_store', fn($r) => $r->fromStore->store_name ?? '-')
                ->addColumn('to_store', fn($r) => $r->toStore->store_name ?? '-')
                ->addColumn('requested_by', fn($r) => $r->requester->name ?? '-')
                ->addColumn('status', function($r) {
                    $badge = sprintf(
                        '<span class="badge %s">%s</span>',
                        $r->getStatusBadgeClass(),
                        ucfirst(str_replace('_', ' ', $r->status))
                    );
                    // Add fulfillment progress for partial
                    if ($r->status === StoreRequisition::STATUS_PARTIAL || $r->status === StoreRequisition::STATUS_APPROVED) {
                        $totalRequested = $r->items->sum('requested_qty');
                        $totalFulfilled = $r->items->sum('fulfilled_qty');
                        if ($totalRequested > 0) {
                            $pct = round(($totalFulfilled / $totalRequested) * 100);
                            $badge .= sprintf(' <small class="text-muted">(%d%%)</small>', $pct);
                        }
                    }
                    return $badge;
                })
                ->addColumn('items_count', function($r) {
                    $total = $r->items->count();
                    $fulfilled = $r->items->where('status', 'fulfilled')->count();
                    if ($fulfilled > 0 && $fulfilled < $total) {
                        return "{$fulfilled}/{$total} items";
                    }
                    return "{$total} items";
                })
                ->addColumn('actions', fn($r) => $this->getActionButtons($r))
                ->rawColumns(['status', 'actions'])
                ->make(true);
        }

        $stores = Store::active()->orderBy('store_name')->get();
        $statuses = StoreRequisition::getStatuses();

        // Build stats with user-based filtering
        $user = auth()->user();
        $baseQuery = StoreRequisition::query();

        if (!$user->hasAnyRole(['admin', 'super-admin', 'store', 'Store'])) {
            $baseQuery->where(function ($q) use ($user) {
                $q->where('requested_by', $user->id)
                  ->orWhere('approved_by', $user->id)
                  ->orWhere('rejected_by', $user->id)
                  ->orWhere('fulfilled_by', $user->id);
            });
        }

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'pending_approval' => (clone $baseQuery)->where('status', StoreRequisition::STATUS_PENDING)->count(),
            'awaiting_fulfillment' => (clone $baseQuery)->whereIn('status', [StoreRequisition::STATUS_APPROVED, StoreRequisition::STATUS_PARTIAL])->count(),
            'fulfilled_this_month' => (clone $baseQuery)->where('status', StoreRequisition::STATUS_FULFILLED)
                ->whereMonth('fulfilled_at', now()->month)
                ->whereYear('fulfilled_at', now()->year)
                ->count(),
        ];

        return view('admin.inventory.requisitions.index', compact('stores', 'statuses', 'stats'));
    }

    /**
     * Show the form for creating a new requisition
     */
    public function create()
    {
        $stores = Store::active()->orderBy('store_name')->get();
        $products = Product::with('price')
            ->where('status', true)
            ->orderBy('product_name')
            ->get();

        return view('admin.inventory.requisitions.create', compact('stores', 'products'));
    }

    /**
     * Store a newly created requisition
     */
    public function store(Request $request)
    {
        $request->validate([
            'from_store_id' => 'required|exists:stores,id',
            'to_store_id' => 'required|exists:stores,id|different:from_store_id',
            'request_notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.requested_qty' => 'required|integer|min:1',
        ]);

        try {
            $requisition = $this->requisitionService->create(
                $request->only(['from_store_id', 'to_store_id', 'request_notes']),
                $request->items
            );

            return response()->json([
                'success' => true,
                'message' => "Requisition {$requisition->requisition_number} created successfully",
                'data' => $requisition,
                'redirect' => route('inventory.requisitions.show', $requisition->id),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create requisition: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified requisition
     */
    public function show(StoreRequisition $requisition)
    {
        $requisition->load([
            'fromStore',
            'toStore',
            'requester',
            'approver',
            'fulfiller',
            'items.product',
            'items.sourceBatch',
            'items.destinationBatch',
        ]);

        // Get available stock info for fulfillment
        $availableStock = null;
        if ($requisition->canFulfill()) {
            $availableStock = $this->requisitionService->getAvailableStockForRequisition($requisition);
        }

        return view('admin.inventory.requisitions.show', compact('requisition', 'availableStock'));
    }

    /**
     * Approve requisition
     */
    public function approve(Request $request, StoreRequisition $requisition)
    {
        $request->validate([
            'approved_qtys' => 'nullable|array',
            'approved_qtys.*' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $requisition = $this->requisitionService->approve(
                $requisition,
                $request->approved_qtys,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Requisition approved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject requisition
     */
    public function reject(Request $request, StoreRequisition $requisition)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $requisition = $this->requisitionService->reject($requisition, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Requisition rejected',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel requisition
     */
    public function cancel(StoreRequisition $requisition)
    {
        try {
            $requisition = $this->requisitionService->cancel($requisition);

            return response()->json([
                'success' => true,
                'message' => 'Requisition cancelled',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Fulfill requisition items
     *
     * Supports multi-batch fulfillment where UI sends:
     * items[{item_id}][requisition_item_id] = item_id
     * items[{item_id}][product_id] = product_id
     * items[{item_id}][batches][{batch_id}] = qty_from_this_batch
     */
    public function fulfill(Request $request, StoreRequisition $requisition)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.requisition_item_id' => 'required|exists:store_requisition_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.batches' => 'nullable|array',
            'items.*.batches.*' => 'nullable|integer|min:0',
        ]);

        try {
            // Transform items array for service
            // Format: [item_id => ['batches' => [batch_id => qty, ...], 'total_qty' => X]]
            $fulfillments = [];

            foreach ($request->items as $itemKey => $itemData) {
                $itemId = $itemData['requisition_item_id'];
                $batches = $itemData['batches'] ?? [];

                // Filter out zero quantities and convert to integers
                $batchFulfillments = [];
                $totalQty = 0;

                foreach ($batches as $batchId => $qty) {
                    $qty = (int) $qty;
                    if ($qty > 0) {
                        $batchFulfillments[$batchId] = $qty;
                        $totalQty += $qty;
                    }
                }

                if ($totalQty > 0) {
                    $fulfillments[$itemId] = [
                        'batches' => $batchFulfillments,
                        'total_qty' => $totalQty,
                    ];
                }
            }

            if (empty($fulfillments)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No items to fulfill. Please enter quantities to transfer.',
                ], 400);
            }

            $batches = $this->requisitionService->fulfill($requisition, $fulfillments);

            $requisition->refresh();

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Fulfilled %d item(s). Status: %s',
                    count($fulfillments),
                    ucfirst($requisition->status)
                ),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fulfill: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available batches for a product in source store
     */
    public function getAvailableBatches(Request $request, StoreRequisition $requisition)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $batches = $this->stockService->getAvailableBatches(
            $request->product_id,
            $requisition->from_store_id
        );

        return response()->json([
            'success' => true,
            'batches' => $batches->map(fn($b) => [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'current_qty' => $b->current_qty,
                'expiry_date' => $b->expiry_date?->format('Y-m-d'),
                'cost_price' => $b->cost_price,
            ]),
        ]);
    }

    /**
     * Get pending requisitions that need approval
     */
    public function pendingApproval()
    {
        $requisitions = $this->requisitionService->getPendingApproval();

        return view('admin.inventory.requisitions.pending-approval', compact('requisitions'));
    }

    /**
     * Get requisitions that need fulfillment for a store
     */
    public function pendingFulfillment(Request $request)
    {
        $storeId = $request->get('store_id', Store::getDefaultPharmacy()?->id);

        if (!$storeId) {
            return redirect()->back()->with('error', 'No store selected');
        }

        $requisitions = $this->requisitionService->getPendingFulfillment($storeId);
        $store = Store::find($storeId);

        return view('admin.inventory.requisitions.pending-fulfillment', compact('requisitions', 'store'));
    }

    /**
     * Generate action buttons for DataTable
     */
    protected function getActionButtons(StoreRequisition $requisition): string
    {
        $buttons = [];

        // View button
        $buttons[] = sprintf(
            '<a href="%s" class="btn btn-sm btn-info" title="View"><i class="mdi mdi-eye"></i></a>',
            route('inventory.requisitions.show', $requisition->id)
        );

        // Approve button (only for pending)
        if ($requisition->canApprove()) {
            $buttons[] = sprintf(
                '<button class="btn btn-sm btn-success" onclick="approveRequisition(%d)" title="Approve"><i class="mdi mdi-check"></i></button>',
                $requisition->id
            );
            $buttons[] = sprintf(
                '<button class="btn btn-sm btn-danger" onclick="rejectRequisition(%d)" title="Reject"><i class="mdi mdi-close"></i></button>',
                $requisition->id
            );
        }

        // Fulfill button (only for approved/partial)
        if ($requisition->canFulfill()) {
            $buttons[] = sprintf(
                '<a href="%s" class="btn btn-sm btn-warning" title="Fulfill"><i class="mdi mdi-truck-delivery"></i></a>',
                route('inventory.requisitions.show', $requisition->id)
            );
        }

        // Cancel button
        if ($requisition->canCancel()) {
            $buttons[] = sprintf(
                '<button class="btn btn-sm btn-secondary" onclick="cancelRequisition(%d)" title="Cancel"><i class="mdi mdi-cancel"></i></button>',
                $requisition->id
            );
        }

        return '<div class="btn-group">' . implode('', $buttons) . '</div>';
    }
}
