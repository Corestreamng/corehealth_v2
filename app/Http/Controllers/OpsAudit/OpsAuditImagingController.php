<?php

namespace App\Http\Controllers\OpsAudit;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\ImagingServiceRequest;
use App\Models\ProductOrServiceRequest;
use App\Models\Payment;

class OpsAuditImagingController extends OpsAuditBaseController
{
    public function index(Request $request)
    {
        $hmos = \App\Models\Hmo::with('scheme')->orderBy('name')->get()->groupBy(fn($hmo) => $hmo->scheme ? $hmo->scheme->name : 'Other Schemes');
        $hmoSchemes = \App\Models\HmoScheme::orderBy('name')->pluck('name', 'id');
        $cashiers = \App\Models\User::role(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER'])->orderBy('firstname')->get()->mapWithKeys(fn($u) => [$u->id => trim($u->firstname . ' ' . ($u->othername ?? '') . ' ' . $u->surname)]);

        return view('admin.ops_audit.imaging', compact('hmos', 'hmoSchemes', 'cashiers'));
    }

    public function data(Request $request, $tab)
    {
        if ($request->isMethod('post') && in_array($request->action, ['bulk_stamp_preview', 'bulk_stamp'])) {
            $modelMap = [
                'requests' => ImagingServiceRequest::class,
                'bills' => ProductOrServiceRequest::class,
                'cashbook' => Payment::class,
            ];
            $request->merge(['zone_key' => 'ops_audit.imaging.' . $tab]);
            return $this->handleBulkStamp($request, $tab, $modelMap);
        }

        switch ($tab) {
            case 'requests':
                return $this->requestsData($request);
            case 'bills':
                return $this->billsData($request);
            case 'cashbook':
                return $this->cashbookData($request);
            default:
                return response()->json(['error' => 'Invalid tab'], 400);
        }
    }

    /**
     * Tab 1: All Imaging Requests
     */
    protected function requestsData(Request $request)
    {
        $query = ImagingServiceRequest::with([
            'patient.user',
            'patient.hmo.scheme',
            'doctor',
            'service',
            'biller',
            'resultBy',
            'approver',
            'productOrServiceRequest.payment.staff_user',
        
            'productOrServiceRequest.payment.user',
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $posr = $row->productOrServiceRequest;
            $payment = $posr?->payment;

            $statusColors = [1 => 'warning text-dark', 2 => 'info', 3 => 'primary', 4 => 'success'];
            $statusTexts = [1 => 'Ordered', 2 => 'Image Captured', 3 => 'Result Entered', 4 => 'Approved'];

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'test' => $row->service?->service_name ?? ($row->is_free_form ? $row->free_form_name : '-'),
                'doctor' => $row->doctor?->firstname ? ($row->doctor->firstname . ' ' . ($row->doctor->surname ?? '')) : '-',
                'status' => '<span class="badge bg-'.($statusColors[$row->status] ?? 'secondary').'">'.($statusTexts[$row->status] ?? $row->status).'</span>',
                'result_by' => $row->resultBy?->firstname ? ($row->resultBy->firstname . ' ' . ($row->resultBy->surname ?? '')) : '-',
                'approved_by' => $row->approver?->firstname ? ($row->approver->firstname . ' ' . ($row->approver->surname ?? '')) : '-',
                'billed_by' => $row->biller?->firstname ? ($row->biller->firstname . ' ' . ($row->biller->surname ?? '')) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'ImagingServiceRequest'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total Scans', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Ordered', 'value' => number_format($all->where('status', 1)->count()), 'color' => '#ffc107'],
                ['label' => 'Approved', 'value' => number_format($all->where('status', 4)->count()), 'color' => '#198754'],
                ['label' => 'Pending Results', 'value' => number_format($all->whereIn('status', [1,2,3])->count()), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 2: Billed Scans
     */
    protected function billsData(Request $request)
    {
        $query = ProductOrServiceRequest::with([
            'patient.user',
            'patient.hmo.scheme',
            'staff',
            'payment.staff_user',
            'service'
        ])->where('type', 'imaging');

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, '');

        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $payment = $row->payment;

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'test' => $row->service?->service_name ?? '-',
                'amount' => '₦' . number_format($row->amount ?? 0, 2),
                'payment_info' => $this->renderPaymentInfo($row),
                                'billed_by' => $row->staff?->firstname ? ($row->staff->firstname . ' ' . ($row->staff->surname ?? '')) : '-',
                'audit' => $this->renderAuditAction($row, 'ProductOrServiceRequest'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total Bills', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Total Amount', 'value' => '₦' . number_format($all->sum('amount'), 2), 'color' => '#6610f2'],
                ['label' => 'Payable', 'value' => '₦' . number_format($all->sum('payable_amount'), 2), 'color' => '#198754'],
                ['label' => 'Claims', 'value' => '₦' . number_format($all->sum('claims_amount'), 2), 'color' => '#0dcaf0'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 3: Cashbook
     */
    protected function cashbookData(Request $request)
    {
        $query = Payment::with([
            'patient.user',
            'staff_user',
            'bank',
            'product_or_service_request'
        ])->whereHas('product_or_service_request', function($q) {
            $q->where('type', 'imaging');
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
