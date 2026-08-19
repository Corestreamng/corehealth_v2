<?php

namespace App\Http\Controllers\OpsAudit;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\MorgueAdmission;
use App\Models\ProductOrServiceRequest;
use App\Models\Payment;

class OpsAuditMorgueController extends OpsAuditBaseController
{
    public function index(Request $request)
    {
        $hmos = \App\Models\Hmo::with('scheme')->orderBy('name')->get()->groupBy(fn($hmo) => $hmo->scheme ? $hmo->scheme->name : 'Other Schemes');
        $hmoSchemes = \App\Models\HmoScheme::orderBy('name')->pluck('name', 'id');
        $cashiers = \App\Models\User::role(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER'])->orderBy('firstname')->get()->mapWithKeys(fn($u) => [$u->id => trim($u->firstname . ' ' . ($u->othername ?? '') . ' ' . $u->surname)]);

        return view('admin.ops_audit.morgue', compact('hmos', 'hmoSchemes', 'cashiers'));
    }

    public function data(Request $request, $tab)
    {
        if ($request->isMethod('post') && in_array($request->action, ['bulk_stamp_preview', 'bulk_stamp'])) {
            $modelMap = [
                'admissions' => MorgueAdmission::class,
                'bills' => ProductOrServiceRequest::class,
                'cashbook' => Payment::class,
            ];
            $request->merge(['zone_key' => 'ops_audit.morgue.' . $tab]);
            return $this->handleBulkStamp($request, $tab, $modelMap);
        }

        switch ($tab) {
            case 'admissions':
                return $this->admissionsData($request);
            case 'bills':
                return $this->billsData($request);
            case 'cashbook':
                return $this->cashbookData($request);
            default:
                return response()->json(['error' => 'Invalid tab'], 400);
        }
    }

    /**
     * Tab 1: Admissions
     */
    protected function admissionsData(Request $request)
    {
        $query = MorgueAdmission::with([
            'patient.user',
            'patient.hmo.scheme',
            'admittedBy',
            'releasedBy',
            'serviceRequest.payment.staff_user'
        
            'serviceRequest.payment.user',
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'serviceRequest');

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $posr = $row->serviceRequest;
            $payment = $posr?->payment;

            $statusColors = ['admitted' => 'warning text-dark', 'released' => 'success'];

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'body_code' => $row->body_code ?? '-',
                'location' => 'F: ' . ($row->fridge_number ?? '-') . ' / T: ' . ($row->tray_number ?? '-'),
                'arrival' => $row->arrival_time ? Carbon::parse($row->arrival_time)->format('d M Y H:i') : '-',
                'release' => $row->release_time ? Carbon::parse($row->release_time)->format('d M Y H:i') : '-',
                'status' => '<span class="badge bg-'.($statusColors[$row->status] ?? 'secondary').'">'.ucfirst($row->status ?? '-').'</span>',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'MorgueAdmission'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total Admitted', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Currently in Morgue', 'value' => number_format($all->where('status', 'admitted')->count()), 'color' => '#dc3545'],
                ['label' => 'Released', 'value' => number_format($all->where('status', 'released')->count()), 'color' => '#198754'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 2: Morgue Bills
     */
    protected function billsData(Request $request)
    {
        $query = ProductOrServiceRequest::with([
            'patient.user',
            'patient.hmo.scheme',
            'staff',
            'payment.staff_user'
        ])->whereHas('patient.morgueAdmissions');

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'serviceRequest');

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
            'product_or_service_request'
        ])->whereHas('product_or_service_request.patient.morgueAdmissions');

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);

        if ($request->filled('cashier_id')) $query->where('user_id', $request->cashier_id);
        if ($request->filled('payment_method')) $query->where('payment_method', $request->payment_method);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            
            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y H:i') : '-',
                'reference' => $row->reference_no ?? '-',
                'patient' => $this->renderPatient($user, $patient, null),
                'total' => '₦' . number_format($row->total ?? 0, 2),
                'audit' => $this->renderAuditAction($row, 'Payment'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total Transactions', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Total Revenue', 'value' => '₦' . number_format($all->sum('total'), 2), 'color' => '#198754'],
            ];
        }, $kpiQuery);
    }
}
