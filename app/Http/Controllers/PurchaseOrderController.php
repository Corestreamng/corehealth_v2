<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderPayment;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Store;
use App\Models\Bank;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Controller: PurchaseOrderController
 *
 * Plan Reference: Phase 4 - Controllers
 * Purpose: Handle purchase order CRUD and workflow operations
 *
 * Permissions Required:
 * - purchase-orders.view: View PO list and details
 * - purchase-orders.create: Create new POs
 * - purchase-orders.edit: Edit draft POs
 * - purchase-orders.approve: Approve submitted POs
 * - purchase-orders.receive: Receive items from POs
 *
 * Related Models: PurchaseOrder, PurchaseOrderItem, Product, Supplier, Store
 * Related Files:
 * - app/Services/PurchaseOrderService.php
 * - resources/views/inventory/purchase_orders/
 */
class PurchaseOrderController extends Controller
{
    protected PurchaseOrderService $purchaseOrderService;

    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
    }

    /**
     * Display a listing of purchase orders
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = PurchaseOrder::with(['supplier', 'targetStore', 'creator', 'items'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }
            if ($request->filled('store_id')) {
                $query->where('target_store_id', $request->store_id);
            }

            return DataTables::of($query)
                ->addColumn('supplier_name', fn($po) => $po->supplier->company_name ?? '-')
                ->addColumn('store_name', fn($po) => $po->targetStore->store_name ?? '-')
                ->addColumn('creator_name', fn($po) => $po->creator->name ?? '-')
                ->addColumn('items_count', fn($po) => $po->items->count() . ' items')
                ->addColumn('status_badge', function($po) {
                    $statusText = ucfirst(str_replace('_', ' ', $po->status));
                    return sprintf(
                        '<span class="badge %s">%s</span>',
                        $po->getStatusBadgeClass(),
                        $statusText
                    );
                })
                ->addColumn('payment_badge', function($po) {
                    if (in_array($po->status, [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED])) {
                        return sprintf(
                            '<span class="badge %s">%s</span>',
                            $po->getPaymentStatusBadgeClass(),
                            ucfirst($po->payment_status ?? 'unpaid')
                        );
                    }
                    return '<span class="badge badge-secondary">N/A</span>';
                })
                ->addColumn('formatted_total', fn($po) => '₦' . number_format($po->total_amount, 2))
                ->addColumn('formatted_date', fn($po) => $po->created_at->format('d M Y'))
                ->addColumn('actions', fn($po) => $this->getActionButtons($po))
                ->rawColumns(['status_badge', 'payment_badge', 'actions'])
                ->make(true);
        }

        $suppliers = Supplier::orderBy('company_name')->get();
        $stores = Store::active()->orderBy('store_name')->get();
        $statuses = PurchaseOrder::getStatuses();
        $statistics = $this->purchaseOrderService->getStatistics('month');

        return view('admin.inventory.purchase-orders.index', compact('suppliers', 'stores', 'statuses', 'statistics'));
    }

    /**
     * Show the form for creating a new purchase order
     */
    public function create()
    {
        $suppliers = Supplier::orderBy('company_name')->get();
        $stores = Store::active()->orderBy('store_name')->get();
        $products = Product::with('price')
            ->where('status', true)
            ->orderBy('product_name')
            ->get();

        return view('admin.inventory.purchase-orders.create', compact('suppliers', 'stores', 'products'));
    }

    /**
     * Store a newly created purchase order
     */
    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'target_store_id' => 'required|exists:stores,id',
            'expected_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.ordered_qty' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'action' => 'nullable|in:save,submit',
        ]);

        try {
            $po = $this->purchaseOrderService->create(
                $request->only(['supplier_id', 'target_store_id', 'expected_date', 'notes']),
                $request->items
            );

            // If action is 'submit', submit the PO for approval
            if ($request->action === 'submit') {
                $po->status = 'submitted';
                $po->submitted_at = now();
                $po->save();
            }

            return response()->json([
                'success' => true,
                'message' => "Purchase Order {$po->po_number} " . ($request->action === 'submit' ? 'submitted' : 'saved as draft') . " successfully",
                'data' => $po,
                'redirect' => route('inventory.purchase-orders.show', $po->id),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create purchase order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified purchase order
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'supplier',
            'targetStore',
            'creator',
            'approver',
            'items.product',
            'expense',
            'payments.creator',
            'payments.bank'
        ]);

        return view('admin.inventory.purchase-orders.show', compact('purchaseOrder'));
    }

    /**
     * Show the form for editing the purchase order
     */
    public function edit(PurchaseOrder $purchaseOrder)
    {
        if (!$purchaseOrder->canEdit()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder->id)
                ->with('error', 'This purchase order cannot be edited');
        }

        $purchaseOrder->load(['items.product']);
        $suppliers = Supplier::orderBy('company_name')->get();
        $stores = Store::active()->orderBy('store_name')->get();
        $products = Product::with('price')
            ->where('status', true)
            ->orderBy('product_name')
            ->get();

        return view('admin.inventory.purchase-orders.edit', compact('purchaseOrder', 'suppliers', 'stores', 'products'));
    }

    /**
     * Update the specified purchase order
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (!$purchaseOrder->canEdit()) {
            return response()->json([
                'success' => false,
                'message' => 'This purchase order cannot be edited',
            ], 403);
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'target_store_id' => 'required|exists:stores,id',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.ordered_qty' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        try {
            $po = $this->purchaseOrderService->update(
                $purchaseOrder,
                $request->only(['supplier_id', 'target_store_id', 'expected_date', 'notes']),
                $request->items
            );

            return response()->json([
                'success' => true,
                'message' => 'Purchase order updated successfully',
                'data' => $po,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update purchase order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit PO for approval
     */
    public function submit(PurchaseOrder $purchaseOrder)
    {
        try {
            $po = $this->purchaseOrderService->submit($purchaseOrder);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order submitted for approval',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Approve PO
     */
    public function approve(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $po = $this->purchaseOrderService->approve($purchaseOrder, $request->notes);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order approved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel PO
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $po = $this->purchaseOrderService->cancel($purchaseOrder, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order cancelled',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Show receiving form
     */
    public function showReceiveForm(PurchaseOrder $purchaseOrder)
    {
        if (!$purchaseOrder->canReceive()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder->id)
                ->with('error', 'This purchase order cannot receive items');
        }

        $purchaseOrder->load(['supplier', 'targetStore', 'items.product']);

        return view('admin.inventory.purchase-orders.receive', compact('purchaseOrder'));
    }

    /**
     * Receive items from PO
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        if (!$purchaseOrder->canReceive()) {
            return response()->json([
                'success' => false,
                'message' => 'This purchase order cannot receive items',
            ], 403);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:purchase_order_items,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.batch_number' => 'required|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.actual_cost' => 'nullable|numeric|min:0',
            'items.*.manufacture_date' => 'nullable|date',
            'receiving_notes' => 'nullable|string',
            'invoice_number' => 'nullable|string|max:100',
            'create_expense' => 'nullable|boolean',
        ]);

        try {
            // Transform items array to expected format
            $receivedItems = [];
            foreach ($request->items as $item) {
                if ($item['qty'] > 0) {
                    $receivedItems[$item['item_id']] = [
                        'qty' => $item['qty'],
                        'actual_cost' => $item['actual_cost'] ?? null,
                        'expiry_date' => $item['expiry_date'] ?? null,
                        'manufacture_date' => $item['manufacture_date'] ?? null,
                        'batch_number' => $item['batch_number'],
                    ];
                }
            }

            if (empty($receivedItems)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No items to receive',
                ], 400);
            }

            $batches = $this->purchaseOrderService->receiveItems($purchaseOrder, $receivedItems);

            // Add receiving notes if provided
            if ($request->receiving_notes) {
                $purchaseOrder->notes = $purchaseOrder->notes
                    ? $purchaseOrder->notes . "\n\nReceiving Notes: " . $request->receiving_notes
                    : "Receiving Notes: " . $request->receiving_notes;
                $purchaseOrder->save();
            }

            $purchaseOrder->refresh();

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Successfully received %d item(s). %d batch(es) created. PO status: %s',
                    count($receivedItems),
                    count($batches),
                    ucfirst($purchaseOrder->status)
                ),
                'batches_created' => count($batches),
                'po_status' => $purchaseOrder->status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to receive items: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get products for AJAX search
     */
    public function searchProducts(Request $request)
    {
        $search = $request->get('q', $request->get('search', ''));
        $storeId = $request->get('store_id');
        $limit = $request->get('limit', 50); // Allow higher limit for grid view

        $query = Product::with(['price', 'category'])
            ->where('status', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                  ->orWhere('product_code', 'like', "%{$search}%");
            });
        }

        $products = $query->limit($limit)
            ->get()
            ->map(function($p) use ($storeId) {
                // Get stock for specific store if provided
                $stock = 0;
                if ($storeId) {
                    $stock = \App\Models\StockBatch::where('product_id', $p->id)
                        ->where('store_id', $storeId)
                        ->where('current_qty', '>', 0)
                        ->sum('current_qty');
                }

                return [
                    'id' => $p->id,
                    'text' => $p->product_name . ' (' . $p->product_code . ')',
                    'product_name' => $p->product_name,
                    'product_code' => $p->product_code,
                    'price' => $p->price->pr_buy_price ?? 0,
                    'category_id' => $p->category_id,
                    'category_name' => $p->category->category_name ?? null,
                    'stock' => $stock,
                ];
            });

        return response()->json($products);
    }

    /**
     * Show payment form for a purchase order
     */
    public function showPaymentForm(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'targetStore', 'items.product', 'payments.creator', 'payments.bank']);

        if (!$purchaseOrder->canRecordPayment()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder->id)
                ->with('error', 'Cannot record payment for this PO. It must be received and have balance due.');
        }

        $banks = Bank::active()->orderBy('name')->get();
        $paymentMethods = PurchaseOrderPayment::getPaymentMethods();

        return view('admin.inventory.purchase-orders.payment', compact(
            'purchaseOrder',
            'banks',
            'paymentMethods'
        ));
    }

    /**
     * Record a payment for a purchase order
     */
    public function recordPayment(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01|max:' . $purchaseOrder->balance_due,
            'payment_method' => 'required|in:cash,bank_transfer,cheque,card',
            'bank_id' => 'nullable|exists:banks,id',
            'reference_number' => 'nullable|string|max:255',
            'cheque_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $payment = $this->purchaseOrderService->recordPayment($purchaseOrder, $request->all());

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment recorded successfully.',
                    'payment' => $payment,
                    'po_status' => $purchaseOrder->fresh()->payment_status,
                    'balance_due' => $purchaseOrder->fresh()->balance_due,
                ]);
            }

            return redirect()->route('purchase-orders.show', $purchaseOrder->id)
                ->with('success', 'Payment recorded successfully.');

        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * List all POs pending payment (Accounts Payable view)
     */
    public function accountsPayable(Request $request)
    {
        if ($request->ajax()) {
            $query = PurchaseOrder::whereIn('status', [PurchaseOrder::STATUS_PARTIAL, PurchaseOrder::STATUS_RECEIVED])
                ->where('payment_status', '!=', PurchaseOrder::PAYMENT_PAID)
                ->with(['supplier', 'targetStore', 'payments']);

            return DataTables::of($query)
                ->addColumn('supplier_name', fn($po) => $po->supplier->company_name ?? '-')
                ->addColumn('store_name', fn($po) => $po->targetStore->store_name ?? '-')
                ->addColumn('formatted_total', fn($po) => '₦' . number_format($po->total_amount, 2))
                ->addColumn('formatted_paid', fn($po) => '₦' . number_format($po->amount_paid, 2))
                ->addColumn('formatted_balance', fn($po) => '₦' . number_format($po->balance_due, 2))
                ->addColumn('payment_status_badge', fn($po) => sprintf(
                    '<span class="badge %s">%s</span>',
                    $po->getPaymentStatusBadgeClass(),
                    ucfirst($po->payment_status)
                ))
                ->addColumn('status_badge', fn($po) => sprintf(
                    '<span class="badge %s">%s</span>',
                    $po->getStatusBadgeClass(),
                    ucfirst($po->status)
                ))
                ->addColumn('formatted_date', fn($po) => $po->created_at->format('d M Y'))
                ->addColumn('actions', function($po) {
                    return sprintf(
                        '<div class="btn-group" role="group">
                            <a href="%s" class="btn btn-sm btn-outline-info" title="View Details">
                                <i class="mdi mdi-eye"></i>
                            </a>
                            <a href="%s" class="btn btn-sm btn-outline-success" title="Record Payment">
                                <i class="mdi mdi-cash-multiple"></i>
                            </a>
                        </div>',
                        route('inventory.purchase-orders.show', $po->id),
                        route('inventory.purchase-orders.payment', $po->id)
                    );
                })
                ->rawColumns(['status_badge', 'payment_status_badge', 'actions'])
                ->make(true);
        }

        return view('admin.inventory.purchase-orders.accounts_payable');
    }

    /**
     * Generate action buttons for DataTable
     */
    protected function getActionButtons(PurchaseOrder $po): string
    {
        $buttons = [];

        // View button
        $buttons[] = sprintf(
            '<a href="%s" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>',
            route('inventory.purchase-orders.show', $po->id)
        );

        // Edit button (only for draft)
        if ($po->canEdit()) {
            $buttons[] = sprintf(
                '<a href="%s" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>',
                route('inventory.purchase-orders.edit', $po->id)
            );
        }

        // Submit button (only for draft with items)
        if ($po->canSubmit()) {
            $buttons[] = sprintf(
                '<button class="btn btn-sm btn-success btn-submit-po" data-id="%d" title="Submit"><i class="fas fa-paper-plane"></i></button>',
                $po->id
            );
        }

        // Approve button (only for submitted)
        if ($po->canApprove()) {
            $buttons[] = sprintf(
                '<button class="btn btn-sm btn-success btn-approve-po" data-id="%d" title="Approve"><i class="fas fa-check"></i></button>',
                $po->id
            );
        }

        // Receive button (only for approved/partial)
        if ($po->canReceive()) {
            $buttons[] = sprintf(
                '<a href="%s" class="btn btn-sm btn-warning" title="Receive"><i class="fas fa-truck-loading"></i></a>',
                route('inventory.purchase-orders.receive', $po->id)
            );
        }

        // Payment button (only for received POs with balance due)
        if ($po->canRecordPayment()) {
            $buttons[] = sprintf(
                '<a href="%s" class="btn btn-sm btn-success" title="Record Payment"><i class="fas fa-money-bill-wave"></i></a>',
                route('inventory.purchase-orders.payment', $po->id)
            );
        }

        // Cancel button
        if ($po->canCancel()) {
            $buttons[] = sprintf(
                '<button class="btn btn-sm btn-danger btn-cancel-po" data-id="%d" title="Cancel"><i class="fas fa-times"></i></button>',
                $po->id
            );
        }

        return '<div class="btn-group">' . implode('', $buttons) . '</div>';
    }
}
