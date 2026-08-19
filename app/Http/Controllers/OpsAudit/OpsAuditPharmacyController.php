<?php

namespace App\Http\Controllers\OpsAudit;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\ProductRequest;
use App\Models\StoreRequisitionItem;
use App\Models\Payment;
use App\Models\Store;

class OpsAuditPharmacyController extends OpsAuditBaseController
{
    public function index(Request $request)
    {
        $hmos = \App\Models\Hmo::with('scheme')->orderBy('name')->get()->groupBy(fn($hmo) => $hmo->scheme ? $hmo->scheme->name : 'Other Schemes');
        $hmoSchemes = \App\Models\HmoScheme::orderBy('name')->pluck('name', 'id');
        $stores = Store::orderBy('store_name')->get()->mapWithKeys(fn($s) => [$s->id => trim($s->store_name . ' (' . $s->distributionRoleLabel() . ')')]);
        $cashiers = \App\Models\User::role(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER'])->orderBy('firstname')->get()->mapWithKeys(fn($u) => [$u->id => trim($u->firstname . ' ' . ($u->othername ?? '') . ' ' . $u->surname)]);

        return view('admin.ops_audit.pharmacy', compact('hmos', 'hmoSchemes', 'stores', 'cashiers'));
    }

    public function data(Request $request, $tab)
    {
        if ($request->isMethod('post') && in_array($request->action, ['bulk_stamp_preview', 'bulk_stamp'])) {
            $modelMap = [
                'dispenses' => ProductRequest::class,
                'returns' => ProductRequest::class,
                'stock' => StoreRequisitionItem::class,
                'requisitions' => \App\Models\StoreRequisition::class,
                'cashbook' => Payment::class,
            ];
            $request->merge(['zone_key' => 'ops_audit.pharmacy.' . $tab]);
            return $this->handleBulkStamp($request, $tab, $modelMap);
        }

        switch ($tab) {
            case 'dispenses':
                return $this->dispensesData($request);
            case 'returns':
                return $this->returnsData($request);
            case 'stock':
                return $this->stockData($request);
            case 'requisitions':
                return $this->moduleRequisitionsData($request, ['roles' => ['pharmacy_hub', 'pharmacy_satellite']]);
            case 'cashbook':
                return $this->cashbookData($request);
            default:
                return response()->json(['error' => 'Invalid tab'], 400);
        }
    }

    /**
     * Tab 1: Dispenses
     */
    protected function dispensesData(Request $request)
    {
        $query = ProductRequest::with([
            'patient.user',
            'patient.hmo.scheme',
            'doctor',
            'product',
            'productOrServiceRequest.payment.staff_user',
            'biller',
            'dispenser'
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);

        // Filter: default to dispensed if not specified
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '>=', 1);
        }
        
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $posr = $row->productOrServiceRequest;
            $payment = $posr?->payment;

            $statusColors = [1 => 'warning text-dark', 2 => 'info', 3 => 'success', 4 => 'danger'];
            $statusTexts = [1 => 'Pending', 2 => 'Approved', 3 => 'Dispensed', 4 => 'Returned'];
            
            $statusHtml = '<span class="badge bg-'.($statusColors[$row->status] ?? 'secondary').'">'.($statusTexts[$row->status] ?? $row->status).'</span>';
            if ($row->biller) {
                $statusHtml .= '<div class="mt-1 text-muted fw-bold" style="font-size:0.7rem;"><i class="mdi mdi-receipt me-1"></i>Billed: ' . trim($row->biller->firstname . ' ' . $row->biller->surname) . '</div>';
            }
            if ($row->dispenser && $row->status >= 3) {
                $statusHtml .= '<div class="mt-1 text-muted fw-bold" style="font-size:0.7rem;"><i class="mdi mdi-pill me-1"></i>Dispensed: ' . trim($row->dispenser->firstname . ' ' . $row->dispenser->surname) . '</div>';
            }

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'doctor' => $row->doctor?->firstname ? ($row->doctor->firstname . ' ' . ($row->doctor->surname ?? '')) : '-',
                'product' => $row->product?->product_name ?? ($row->is_free_form ? $row->free_form_name : '-'),
                'qty' => $row->qty ?? '-',
                'status' => $statusHtml,
                'payable' => $posr ? '₦' . number_format($posr->payable_amount ?? 0, 2) : '-',
                'claims' => $posr ? '₦' . number_format($posr->claims_amount ?? 0, 2) : '-',
                'cashier' => $payment?->staff_user?->firstname ? ($payment->staff_user->firstname . ' ' . ($payment->staff_user->surname ?? '')) : '-',
                'method' => $payment?->payment_method ? '<span class="badge bg-light text-dark border">' . $payment->payment_method . '</span>' : '-',
                'pay_status' => $payment ? '<span class="badge bg-success">Paid</span>' : ($posr ? '<span class="badge bg-warning text-dark">Unpaid</span>' : '-'),
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'ProductRequest'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total Prescriptions', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Dispensed', 'value' => number_format($all->where('status', 3)->count()), 'color' => '#198754'],
                ['label' => 'Pending/Approved', 'value' => number_format($all->whereIn('status', [1,2])->count()), 'color' => '#ffc107'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 2: Returns & Damages
     */
    protected function returnsData(Request $request)
    {
        $query = ProductRequest::with([
            'patient.user',
            'product',
            'biller',
            'dispenser'
        ])->where(function($q) {
            $q->where('status', 4)->orWhere('damaged_qty', '>', 0);
        });

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            
            $type = $row->status == 4 ? '<span class="badge bg-warning text-dark">Return</span>' : '<span class="badge bg-danger">Damage</span>';
            if ($row->biller) {
                $type .= '<div class="mt-1 text-muted fw-bold" style="font-size:0.7rem;"><i class="mdi mdi-receipt me-1"></i>Billed: ' . trim($row->biller->firstname . ' ' . $row->biller->surname) . '</div>';
            }
            if ($row->dispenser) {
                $type .= '<div class="mt-1 text-muted fw-bold" style="font-size:0.7rem;"><i class="mdi mdi-pill me-1"></i>Dispensed: ' . trim($row->dispenser->firstname . ' ' . $row->dispenser->surname) . '</div>';
            }

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, null),
                'product' => $row->product?->product_name ?? ($row->is_free_form ? $row->free_form_name : '-'),
                'type' => $type,
                'qty' => $row->returned_qty > 0 ? $row->returned_qty : $row->damaged_qty,
                'reason' => $row->return_reason ?? $row->damage_reason ?? '-',
                'condition' => $row->return_condition ?? '-',
                'refund' => '₦' . number_format($row->refund_amount ?? 0, 2),
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'ProductRequest'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total Returns/Damages', 'value' => number_format($all->count()), 'color' => '#dc3545'],
                ['label' => 'Returned Qty', 'value' => number_format($all->sum('returned_qty')), 'color' => '#ffc107'],
                ['label' => 'Damaged Qty', 'value' => number_format($all->sum('damaged_qty')), 'color' => '#dc3545'],
                ['label' => 'Total Refunds', 'value' => '₦' . number_format($all->sum('refund_amount'), 2), 'color' => '#0d6efd'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 3: Stock Received (Store Requisitions)
     */
    protected function stockData(Request $request)
    {
        $query = StoreRequisitionItem::with([
            'requisition',
            'product',
        
            
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);

        if ($request->filled('status')) $query->where('status', $request->status);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $statusColors = ['pending' => 'warning text-dark', 'approved' => 'info', 'fulfilled' => 'success', 'rejected' => 'danger'];
            
            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'requisition_no' => $row->requisition?->requisition_no ?? '-',
                'product' => $row->product?->product_name ?? '-',
                'requested_qty' => $row->requested_qty ?? '-',
                'approved_qty' => $row->approved_qty ?? '-',
                'fulfilled_qty' => $row->fulfilled_qty ?? '-',
                'status' => '<span class="badge bg-'.($statusColors[$row->status] ?? 'secondary').'">'.ucfirst($row->status ?? '-').'</span>',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'StoreRequisitionItem'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total Items Req', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Fulfilled', 'value' => number_format($all->where('status', 'fulfilled')->count()), 'color' => '#198754'],
                ['label' => 'Pending', 'value' => number_format($all->where('status', 'pending')->count()), 'color' => '#ffc107'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 4: Cashbook
     */
    protected function cashbookData(Request $request)
    {
        $query = Payment::with([
            'patient.user',
            'staff_user',
            'bank',
            'product_or_service_request'
        ])->whereHas('product_or_service_request', function($q) {
            $q->where('type', 'product');
        });

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'self_payment');

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            
            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y H:i') : '-',
                'reference' => $row->reference_no ?? '-',
                'patient' => $this->renderPatient($user, $patient, null),
                'total' => '₦' . number_format($row->total ?? 0, 2),
                'method' => $row->payment_method ? '<span class="badge bg-light text-dark border">'.$row->payment_method.'</span>' : '-',
                'cashier' => $row->staff_user?->firstname ? ($row->staff_user->firstname . ' ' . ($row->staff_user->surname ?? '')) : '-',
                'bank' => $this->renderBankDetails($row),
                'entity' => $this->renderPaymentEntityDetails($row),
                'audit' => $this->renderAuditAction($row, 'Payment'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total Transactions', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Total Revenue', 'value' => '₦' . number_format($all->sum('total'), 2), 'color' => '#198754'],
                ['label' => 'Cash', 'value' => '₦' . number_format($all->where('payment_method', 'cash')->sum('total'), 2), 'color' => '#ffc107'],
                ['label' => 'POS/Transfer', 'value' => '₦' . number_format($all->whereIn('payment_method', ['pos', 'transfer'])->sum('total'), 2), 'color' => '#6610f2'],
            ];
        }, $kpiQuery);
    }
}
