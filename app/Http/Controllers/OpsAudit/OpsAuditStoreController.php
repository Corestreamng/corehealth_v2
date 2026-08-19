<?php

namespace App\Http\Controllers\OpsAudit;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\StoreRequisition;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use App\Models\AuditMark;

class OpsAuditStoreController extends OpsAuditBaseController
{
    public function index(Request $request)
    {
        $stores = \App\Models\Store::orderBy('store_name')->get()->mapWithKeys(fn($s) => [$s->id => trim($s->store_name . ' (' . $s->distributionRoleLabel() . ')')]);
        $users = \App\Models\User::role(['SUPERADMIN', 'ADMIN', 'STORE', 'PHARMACY'])->orderBy('firstname')->get()->mapWithKeys(fn($u) => [$u->id => trim($u->firstname . ' ' . ($u->othername ?? '') . ' ' . $u->surname)]);
        $suppliers = \App\Models\Supplier::orderBy('company_name')->pluck('company_name', 'id');
        $products = \App\Models\Product::orderBy('product_name')->pluck('product_name', 'id');

        return view('admin.ops_audit.store', compact('stores', 'users', 'suppliers', 'products'));
    }

    public function data(Request $request, $tab)
    {
        if ($request->isMethod('post') && in_array($request->action, ['bulk_stamp_preview', 'bulk_stamp'])) {
            return $this->handleBulkStamp($request, $tab);
        }

        switch ($tab) {
            case 'requisitions':
                return $this->requisitionsData($request);
            case 'purchase_orders':
                return $this->purchaseOrdersData($request);
            case 'batches':
                return $this->batchesData($request);
            default:
                return response()->json(['error' => 'Invalid tab'], 400);
        }
    }

    protected function requisitionsData(Request $request)
    {
        $query = StoreRequisition::with([
            'fromStore',
            'toStore',
            'requestedBy',
            'approvedBy',
            'fulfilledBy',
            'items' // If we want to sum values
        ]);

        $this->applyDateFilter($query, $request);

        if ($request->filled('from_store_id')) $query->where('from_store_id', $request->from_store_id);
        if ($request->filled('to_store_id')) $query->where('to_store_id', $request->to_store_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('requested_by')) $query->where('requested_by', $request->requested_by);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $statusColors = ['pending' => 'warning text-dark', 'approved' => 'info', 'fulfilled' => 'success', 'rejected' => 'danger'];
            $sColor = $statusColors[$row->status] ?? 'secondary';

            // Calculate total value if items loaded, otherwise generic
            $totalValue = $row->items ? $row->items->sum(fn($i) => ($i->supplied_qty ?? $i->requested_qty) * ($i->unit_cost ?? 0)) : 0;

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'req_no' => $row->requisition_number ?? '-',
                'from_store' => $row->fromStore ? ($row->fromStore->store_name . '<br><small class="text-muted">' . $row->fromStore->distributionRoleLabel() . '</small>') : '-',
                'to_store' => $row->toStore ? ($row->toStore->store_name . '<br><small class="text-muted">' . $row->toStore->distributionRoleLabel() . '</small>') : '-',
                'status' => '<span class="badge bg-' . $sColor . '">' . ucfirst($row->status ?? '-') . '</span>',
                'requested_by' => $row->requestedBy?->firstname ? ($row->requestedBy->firstname . ' ' . ($row->requestedBy->surname ?? '')) : '-',
                'approved_by' => $row->approvedBy?->firstname ? ($row->approvedBy->firstname . ' ' . ($row->approvedBy->surname ?? '')) : '-',
                'fulfilled_by' => $row->fulfilledBy?->firstname ? ($row->fulfilledBy->firstname . ' ' . ($row->fulfilledBy->surname ?? '')) : '-',
                'items_count' => $row->items ? $row->items->count() : '-',
                'total_value' => '₦' . number_format($totalValue, 2),
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'StoreRequisition'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total Requisitions', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Pending', 'value' => number_format($all->where('status', 'pending')->count()), 'color' => '#ffc107'],
                ['label' => 'Approved', 'value' => number_format($all->where('status', 'approved')->count()), 'color' => '#0dcaf0'],
                ['label' => 'Fulfilled', 'value' => number_format($all->where('status', 'fulfilled')->count()), 'color' => '#198754'],
                ['label' => 'Rejected', 'value' => number_format($all->where('status', 'rejected')->count()), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    protected function purchaseOrdersData(Request $request)
    {
        $query = PurchaseOrder::with([
            'supplier',
            'targetStore',
            'createdBy',
            'approvedBy'
        ]);

        $this->applyDateFilter($query, $request);

        if ($request->filled('supplier_id')) $query->where('supplier_id', $request->supplier_id);
        if ($request->filled('target_store_id')) $query->where('target_store_id', $request->target_store_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('payment_status')) $query->where('payment_status', $request->payment_status);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $statusColors = ['draft' => 'secondary', 'submitted' => 'warning text-dark', 'approved' => 'info', 'received' => 'success', 'cancelled' => 'danger'];
            $sColor = $statusColors[$row->status] ?? 'secondary';

            $payColors = ['unpaid' => 'warning text-dark', 'partial' => 'info', 'paid' => 'success'];
            $pColor = $payColors[$row->payment_status] ?? 'secondary';

            $balance = max(0, ($row->total_amount ?? 0) - ($row->amount_paid ?? 0));

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'po_no' => $row->po_number ?? '-',
                'supplier' => $row->supplier?->company_name ?? '-',
                'store' => $row->targetStore ? ($row->targetStore->store_name . '<br><small class="text-muted">' . $row->targetStore->distributionRoleLabel() . '</small>') : '-',
                'status' => '<span class="badge bg-' . $sColor . '">' . ucfirst($row->status ?? '-') . '</span>',
                'pay_status' => '<span class="badge bg-' . $pColor . '">' . ucfirst(str_replace('_', ' ', $row->payment_status ?? '-')) . '</span>',
                'total' => '₦' . number_format($row->total_amount ?? 0, 2),
                'paid' => '₦' . number_format($row->amount_paid ?? 0, 2),
                'balance' => '₦' . number_format($balance, 2),
                'created_by' => $row->createdBy?->firstname ? ($row->createdBy->firstname . ' ' . ($row->createdBy->surname ?? '')) : '-',
                'approved_by' => $row->approvedBy?->firstname ? ($row->approvedBy->firstname . ' ' . ($row->approvedBy->surname ?? '')) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'PurchaseOrder'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total POs', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Draft/Submitted', 'value' => number_format($all->whereIn('status', ['draft', 'submitted'])->count()), 'color' => '#ffc107'],
                ['label' => 'Approved/Received', 'value' => number_format($all->whereIn('status', ['approved', 'received'])->count()), 'color' => '#198754'],
                ['label' => 'Total Value', 'value' => '₦' . number_format($all->sum('total_amount'), 2), 'color' => '#6610f2'],
                ['label' => 'Outstanding', 'value' => '₦' . number_format($all->sum('total_amount') - $all->sum('amount_paid'), 2), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    protected function batchesData(Request $request)
    {
        $query = StockBatch::with([
            'store',
            'product',
            'createdBy'
        ])->where('source', 'manual');

        $this->applyDateFilter($query, $request);

        if ($request->filled('store_id')) $query->where('store_id', $request->store_id);
        if ($request->filled('product_id')) $query->where('product_id', $request->product_id);
        if ($request->filled('created_by')) $query->where('created_by', $request->created_by);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $totalValue = ($row->initial_qty ?? 0) * ($row->cost_price ?? 0);

            $expiryColor = 'text-dark';
            if ($row->expiry_date) {
                if (Carbon::parse($row->expiry_date)->isPast()) $expiryColor = 'text-danger font-weight-bold';
                elseif (Carbon::parse($row->expiry_date)->isBefore(now()->addMonths(3))) $expiryColor = 'text-warning font-weight-bold';
            }

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'store' => $row->store ? ($row->store->store_name . '<br><small class="text-muted">' . $row->store->distributionRoleLabel() . '</small>') : '-',
                'product' => $row->product?->product_name ?? '-',
                'batch_no' => $row->batch_number ?? '-',
                'qty' => number_format($row->initial_qty ?? 0),
                'unit_cost' => '₦' . number_format($row->cost_price ?? 0, 2),
                'total_value' => '₦' . number_format($totalValue, 2),
                'expiry' => '<span class="' . $expiryColor . '">' . ($row->expiry_date ? Carbon::parse($row->expiry_date)->format('d M Y') : '-') . '</span>',
                'created_by' => $row->createdBy?->firstname ? ($row->createdBy->firstname . ' ' . ($row->createdBy->surname ?? '')) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'StockBatch'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            $totalValue = $all->sum(fn($r) => ($r->initial_qty ?? 0) * ($r->cost_price ?? 0));
            $expiring = $all->filter(fn($r) => $r->expiry_date && Carbon::parse($r->expiry_date)->isBefore(now()->addMonths(3)))->count();

            return [
                ['label' => 'Total Manual Batches', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Total Qty', 'value' => number_format($all->sum('initial_qty')), 'color' => '#198754'],
                ['label' => 'Total Value', 'value' => '₦' . number_format($totalValue, 2), 'color' => '#6610f2'],
                ['label' => 'Expiring < 3 months', 'value' => number_format($expiring), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    protected function handleBulkStamp(Request $request, $tab)
    {
        $modelMap = [
            'requisitions' => StoreRequisition::class,
            'purchase_orders' => PurchaseOrder::class,
            'batches' => StockBatch::class,
        ];

        $request->merge(['zone_key' => 'ops_audit.store.' . $tab]);
        return $this->processBulkStamp($request, $tab, $modelMap);
    }
}
