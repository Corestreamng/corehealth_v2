<?php

namespace App\Http\Controllers;

use App\Models\StoreRequisition;
use App\Models\StoreRequisitionItem;
use App\Models\Product;
use App\Models\Store;
use App\Services\RequisitionService;
use App\Services\StockService;
use App\Services\StoreContextResolver;
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
    protected StoreContextResolver $resolver;

    public function __construct(RequisitionService $requisitionService, StockService $stockService, StoreContextResolver $resolver)
    {
        $this->requisitionService = $requisitionService;
        $this->stockService = $stockService;
        $this->resolver = $resolver;
    }

    /**
     * Display a listing of requisitions
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $user = auth()->user();
            $isAdmin = $user->hasAnyRole(['ADMIN', 'SUPERADMIN', 'super-admin', 'store', 'Store']);

            // ── Store Governance: scope to stores the user can work from ─────────
            // Admins see everything; others see requisitions for any store in their
            // candidateStores() set (both as requester and as fulfiller side).
            $candidateIds = $isAdmin
                ? null
                : $this->resolver->candidateStores($user)->pluck('id');

            $query = StoreRequisition::with(['fromStore', 'toStore', 'requester', 'items'])
                ->orderBy('created_at', 'desc');

            if (! $isAdmin && $candidateIds !== null) {
                $query->where(function ($q) use ($candidateIds) {
                    $q->whereIn('to_store_id', $candidateIds)   // requests made for my store(s)
                        ->orWhereIn('from_store_id', $candidateIds); // requests my store(s) must fulfill
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
                ->addColumn('status', function ($r) {
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
                ->addColumn('items_count', function ($r) {
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

        $stores = Store::active()->forUser(auth()->user())->orderBy('store_name')->get();
        $statuses = StoreRequisition::getStatuses();

        // Build stats scoped to the user's candidate stores
        $user = auth()->user();
        $isAdmin = $user->hasAnyRole(['ADMIN', 'SUPERADMIN', 'super-admin', 'store', 'Store']);
        $candidateIds = $isAdmin
            ? null
            : $this->resolver->candidateStores($user)->pluck('id');

        $baseQuery = StoreRequisition::query();
        if (! $isAdmin && $candidateIds !== null) {
            $baseQuery->where(function ($q) use ($candidateIds) {
                $q->whereIn('to_store_id', $candidateIds)
                    ->orWhereIn('from_store_id', $candidateIds);
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
        $user          = auth()->user();
        $resolvedStore = $this->resolver->resolve($user);
        $myStores      = $this->resolver->candidateStores($user); // stores I'm requesting FOR

        // Active stores accessible to this user — used for source (from_store) cards with lane-awareness.
        // Server-side lane policy and to_store ownership check in store() enforce the actual rules.
        $stores = Store::active()->forUser(auth()->user())->orderBy('store_name')->get();

        $products = Product::with('price')
            ->where('status', true)
            ->orderBy('product_name')
            ->get();

        // Compute per-store stats for store selection cards
        $storeStats = [];
        foreach ($stores as $store) {
            $storeStockQuery = \App\Models\StoreStock::where('store_id', $store->id)->where('is_active', true);
            $totalProducts = (clone $storeStockQuery)->where('current_quantity', '>', 0)->count();
            $totalStock    = (clone $storeStockQuery)->sum('current_quantity');
            $lowStock      = (clone $storeStockQuery)->whereColumn('current_quantity', '<=', 'reorder_level')->where('current_quantity', '>', 0)->count();
            $outOfStock    = (clone $storeStockQuery)->where('current_quantity', '<=', 0)->count();
            $storeStats[$store->id] = [
                'products' => $totalProducts,
                'stock'    => $totalStock,
                'low'      => $lowStock,
                'out'      => $outOfStock,
            ];
        }

        return view('admin.inventory.requisitions.create', compact(
            'stores',
            'products',
            'storeStats',
            'resolvedStore',
            'myStores'
        ));
    }

    /**
     * Store a newly created requisition
     *
     * Plan §5.2 Step 1, §7.1: lane policy Gate injected here before RequisitionService::create().
     * RequisitionService::create() itself is NOT modified.
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
            'items.*.packaging_id' => 'nullable|exists:product_packagings,id',
            'items.*.packaging_qty' => 'nullable|numeric|min:0',
        ]);

        // ── Store Governance: verify to_store is accessible to this user ────────
        // Admins (ADMIN, SUPERADMIN, super-admin) bypass this check.
        if (! auth()->user()->hasAnyRole(['ADMIN', 'SUPERADMIN', 'super-admin', 'store', 'Store', 'STORE'])) {
            $accessibleStoreIds = \App\Models\Store::active()->forUser(auth()->user())->pluck('id');
            if (! $accessibleStoreIds->contains((int) $request->to_store_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorised to request stock for the selected destination store.',
                ], 403);
            }
        }

        // ── Store Governance Gate (Plan §5.2 Step 1) ──────────────────────────
        // Validates that source_role → destination_role is an allowed lane.
        // 403 JSON response with human-readable denyReason() if blocked.
        // Does NOT modify RequisitionService::create().
        $sourceStore      = \App\Models\Store::findOrFail($request->from_store_id);
        $destinationStore = \App\Models\Store::findOrFail($request->to_store_id);

        $laneCheck = \Illuminate\Support\Facades\Gate::inspect(
            'requisition-lane-allowed',
            [$sourceStore->distribution_role, $destinationStore->distribution_role]
        );

        if ($laneCheck->denied()) {
            return response()->json([
                'success'    => false,
                'message'    => $laneCheck->message(),
                'lane_error' => true,  // used by UI to show the lane-policy banner (Plan §7.1 UI)
            ], 403);
        }

        // ── Plan §R12, §C2 — Lane Override ShiftAction Logging ───────────────
        // If the lane matrix would have blocked this pair but the Gate allowed it
        // due to store-policy.override-lane permission, log a ShiftAction.
        if (auth()->user()->hasPermissionTo('store-policy.override-lane')) {
            $lanePolicy = \App\Models\StoreLanePolicy::check(
                $sourceStore->distribution_role,
                $destinationStore->distribution_role
            );
            if (! $lanePolicy->allowed) {
                $activeShift = \App\Models\NursingShift::where('user_id', auth()->id())
                    ->where('status', 'active')
                    ->latest()
                    ->first();

                if ($activeShift) {
                    \App\Models\ShiftAction::create([
                        'shift_id'       => $activeShift->id,
                        'user_id'        => auth()->id(),
                        'action_type'    => 'other',
                        'action_subtype' => 'lane_override',
                        'description'    => 'Requisition created with overridden lane policy',
                        'details'        => 'Source role: ' . $sourceStore->distribution_role . ' → Dest role: ' . $destinationStore->distribution_role . ' (normally blocked)',
                        'auditable_type' => \App\Models\Store::class,
                        'auditable_id'   => $sourceStore->id,
                        'metadata'       => [
                            'source_store_id'     => $sourceStore->id,
                            'destination_store_id' => $destinationStore->id,
                            'source_role'         => $sourceStore->distribution_role,
                            'destination_role'    => $destinationStore->distribution_role,
                            'override_permission' => 'store-policy.override-lane',
                        ],
                        'is_critical'    => false,
                        'created_at'     => now(),
                    ]);
                }
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        try {
            $requisition = $this->requisitionService->create(
                $request->only(['from_store_id', 'to_store_id', 'request_notes']),
                $request->items
            );

            // Auto-approve if requested (e.g. from Tally Card)
            if ($request->boolean('auto_approve')) {
                $this->requisitionService->approve($requisition, null, 'Auto-approved via Store Workbench');
            }

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
            'items.packaging',
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
     *
     * Plan §5.2 Step 2, §7.2: manager Gate injected here before RequisitionService::approve().
     * RequisitionService::approve() itself is NOT modified.
     */
    public function approve(Request $request, StoreRequisition $requisition)
    {
        $request->validate([
            'approved_qtys' => 'nullable|array',
            'approved_qtys.*' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        // ── Store Governance Gate (Plan §5.2 Step 2) ──────────────────────────
        // Approver must be the manager of the SOURCE store (the store being drawn from).
        // Does NOT modify RequisitionService::approve().
        $sourceStore = $requisition->fromStore;
        $approvalCheck = \Illuminate\Support\Facades\Gate::inspect(
            'can-approve-requisition-for-store',
            $sourceStore
        );

        if ($approvalCheck->denied()) {
            return response()->json([
                'success' => false,
                'message' => $approvalCheck->message(),
            ], 403);
        }
        // ─────────────────────────────────────────────────────────────────────

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

            // ── Plan §R11, §C2 — FIFO Override ShiftAction Logging ───────────────
            // Detect when the user fulfills from a non-oldest batch while an earlier
            // batch still had stock. Log a ShiftAction for audit trail.
            // RequisitionService::fulfill() is NOT modified per plan constraint.
            if (auth()->user()->hasPermissionTo('store-policy.override-fifo')) {
                foreach ($fulfillments as $itemId => $fulfillData) {
                    $reqItem = \App\Models\StoreRequisitionItem::find($itemId);
                    if (! $reqItem) continue;

                    $batchesUsed = array_keys($fulfillData['batches']);

                    // Load all active batches for this product in FIFO order
                    $allBatches = \App\Models\StockBatch::where('product_id', $reqItem->product_id)
                        ->where('store_id', $requisition->from_store_id)
                        ->where('is_active', true)
                        ->where('current_qty', '>', 0)
                        ->orderBy('expiry_date', 'asc')
                        ->orderBy('created_at', 'asc')
                        ->pluck('id')
                        ->toArray();

                    // Find first batch in FIFO order not fully used
                    $firstBatchId = $allBatches[0] ?? null;
                    if ($firstBatchId && ! in_array($firstBatchId, $batchesUsed)) {
                        // Non-FIFO: log ShiftAction
                        $activeShift = \App\Models\NursingShift::where('user_id', auth()->id())
                            ->where('status', 'active')
                            ->latest()
                            ->first();

                        if ($activeShift) {
                            \App\Models\ShiftAction::create([
                                'shift_id'     => $activeShift->id,
                                'user_id'      => auth()->id(),
                                'action_type'  => 'other',
                                'action_subtype' => 'fifo_override',
                                'description'  => 'FIFO/FEFO batch order overridden during requisition fulfillment',
                                'details'      => 'Requisition #' . $requisition->id . ', product_id=' . $reqItem->product_id . '. FIFO batch ' . $firstBatchId . ' skipped.',
                                'auditable_type' => \App\Models\StoreRequisition::class,
                                'auditable_id'   => $requisition->id,
                                'metadata'     => [
                                    'requisition_id'   => $requisition->id,
                                    'product_id'       => $reqItem->product_id,
                                    'fifo_batch_id'    => $firstBatchId,
                                    'batches_used'     => $batchesUsed,
                                    'override_permission' => 'store-policy.override-fifo',
                                ],
                                'is_critical'  => false,
                                'created_at'   => now(),
                            ]);
                        }
                    }
                }
            }
            // ─────────────────────────────────────────────────────────────────────

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
        // If product_id is provided, return batches for that specific product (legacy/singular use)
        if ($request->filled('product_id')) {
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

        // Otherwise return all fulfillable items with their available batches (fulfillment modal use)
        $items = $this->requisitionService->getAvailableStockForRequisition($requisition);

        return response()->json([
            'success' => true,
            'items' => $items->map(fn($i) => [
                'id' => $i['item_id'],
                'product_id' => $i['product']->id,
                'product_name' => $i['product']->product_name,
                'requested_qty' => $i['requested_qty'],
                'approved_qty' => $i['approved_qty'],
                'fulfilled_qty' => $i['fulfilled_qty'],
                'remaining_qty' => $i['remaining_qty'],
                'available_batches' => collect($i['batches'])->map(fn($b) => [
                    'id' => $b['id'],
                    'batch_number' => $b['batch_number'],
                    'current_qty' => $b['current_qty'],
                    'expiry_date' => $b['expiry_date'],
                ]),
                'packaging' => $i['product']->packagings->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'base_unit_qty' => $p->base_unit_qty,
                ]),
            ]),
        ]);
    }

    /**
     * Get pending requisitions that need approval
     */
    public function pendingApproval()
    {
        $user     = auth()->user();
        $isAdmin  = $user->hasAnyRole(['ADMIN', 'SUPERADMIN', 'super-admin', 'store', 'Store']);

        // ── Governance: only show requisitions drawn FROM my candidate stores ──
        // Admins see all. Others only see what they can act on.
        if ($isAdmin) {
            $requisitions = $this->requisitionService->getPendingApproval();
        } else {
            $candidateIds = $this->resolver->candidateStores($user)->pluck('id');
            $requisitions = \App\Models\StoreRequisition::pending()
                ->whereIn('from_store_id', $candidateIds)
                ->with(['items.product', 'items.packaging', 'fromStore', 'toStore', 'requester'])
                ->orderBy('created_at', 'asc')
                ->get();
        }

        return view('admin.inventory.requisitions.pending-approval', compact('requisitions'));
    }

    /**
     * Get requisitions that need fulfillment for a store
     */
    public function pendingFulfillment(Request $request)
    {
        $user     = auth()->user();
        $isAdmin  = $user->hasAnyRole(['ADMIN', 'SUPERADMIN', 'super-admin', 'store', 'Store']);
        $myStores = $isAdmin ? null : $this->resolver->candidateStores($user);

        // ── Governance: resolve the active store for this session ─────────────
        // Priority: explicit query param (for manual tab switch) → resolved store
        // → first candidate → pharmacy default fallback.
        if ($request->filled('store_id')) {
            $storeId = (int) $request->get('store_id');
            // Non-admins must only be able to view their own candidate stores
            if (! $isAdmin && $myStores && ! $myStores->contains('id', $storeId)) {
                abort(403, 'You cannot view fulfillment for that store.');
            }
        } else {
            $resolvedStore = $this->resolver->resolve($user);
            $storeId = $resolvedStore?->id
                ?? ($myStores && $myStores->isNotEmpty() ? $myStores->first()->id : null)
                ?? Store::getDefaultPharmacy()?->id;
        }

        if (! $storeId) {
            return redirect()->back()->with('error', 'No store resolved. Please set your store context first.');
        }

        $requisitions = $this->requisitionService->getPendingFulfillment($storeId);
        $store        = Store::find($storeId);

        return view('admin.inventory.requisitions.pending-fulfillment', compact(
            'requisitions',
            'store',
            'myStores'
        ));
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
