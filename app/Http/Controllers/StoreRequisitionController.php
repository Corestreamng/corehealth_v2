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
            $query = StoreRequisition::with(['fromStore', 'toStore', 'requester'])
                ->orderBy('created_at', 'desc');

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
                ->addColumn('from_store_name', fn($r) => $r->fromStore->store_name ?? '-')
                ->addColumn('to_store_name', fn($r) => $r->toStore->store_name ?? '-')
                ->addColumn('requester_name', fn($r) => $r->requester->name ?? '-')
                ->addColumn('status_badge', fn($r) => sprintf(
                    '<span class="badge %s">%s</span>',
                    $r->getStatusBadgeClass(),
                    ucfirst($r->status)
                ))
                ->addColumn('items_count', fn($r) => $r->items->count() . ' items')
                ->addColumn('formatted_date', fn($r) => $r->created_at->format('d M Y H:i'))
                ->addColumn('actions', fn($r) => $this->getActionButtons($r))
                ->rawColumns(['status_badge', 'actions'])
                ->make(true);
        }

        $stores = Store::active()->orderBy('store_name')->get();
        $statuses = StoreRequisition::getStatuses();
        $statistics = $this->requisitionService->getStatistics();

        return view('admin.inventory.requisitions.index', compact('stores', 'statuses', 'statistics'));
    }

    /**
     * Show the form for creating a new requisition
     */
    public function create()
    {
        $stores = Store::active()->orderBy('store_name')->get();
        $products = Product::with('price')
            ->where('visible', true)
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
                'redirect' => route('requisitions.show', $requisition->id),
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
     */
    public function fulfill(Request $request, StoreRequisition $requisition)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:store_requisition_items,id',
            'items.*.qty' => 'required|integer|min:0',
            'items.*.batch_id' => 'nullable|exists:stock_batches,id',
        ]);

        try {
            // Transform items array
            $fulfillments = [];
            foreach ($request->items as $item) {
                $fulfillments[$item['item_id']] = [
                    'qty' => $item['qty'],
                    'batch_id' => $item['batch_id'] ?? null,
                ];
            }

            $batches = $this->requisitionService->fulfill($requisition, $fulfillments);

            $requisition->refresh();

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Fulfilled %d items. Status: %s',
                    count($batches),
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
            '<a href="%s" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>',
            route('requisitions.show', $requisition->id)
        );

        // Approve button (only for pending)
        if ($requisition->canApprove()) {
            $buttons[] = sprintf(
                '<button class="btn btn-sm btn-success btn-approve-req" data-id="%d" title="Approve"><i class="fas fa-check"></i></button>',
                $requisition->id
            );
            $buttons[] = sprintf(
                '<button class="btn btn-sm btn-danger btn-reject-req" data-id="%d" title="Reject"><i class="fas fa-times"></i></button>',
                $requisition->id
            );
        }

        // Fulfill button (only for approved/partial)
        if ($requisition->canFulfill()) {
            $buttons[] = sprintf(
                '<button class="btn btn-sm btn-warning btn-fulfill-req" data-id="%d" title="Fulfill"><i class="fas fa-truck"></i></button>',
                $requisition->id
            );
        }

        // Cancel button
        if ($requisition->canCancel()) {
            $buttons[] = sprintf(
                '<button class="btn btn-sm btn-secondary btn-cancel-req" data-id="%d" title="Cancel"><i class="fas fa-ban"></i></button>',
                $requisition->id
            );
        }

        return '<div class="btn-group">' . implode('', $buttons) . '</div>';
    }
}
