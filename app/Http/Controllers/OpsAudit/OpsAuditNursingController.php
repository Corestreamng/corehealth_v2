<?php

namespace App\Http\Controllers\OpsAudit;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\AdmissionRequest;
use App\Models\NursingNote;
use App\Models\ProductOrServiceRequest;

class OpsAuditNursingController extends OpsAuditBaseController
{
    public function index(Request $request)
    {
        $wards = \App\Models\Ward::orderBy('name')->pluck('name', 'id');
        $hmos = \App\Models\Hmo::with('scheme')->orderBy('name')->get()->groupBy(fn($hmo) => $hmo->scheme ? $hmo->scheme->name : 'Other Schemes');
        $hmoSchemes = \App\Models\HmoScheme::orderBy('name')->pluck('name', 'id');

        return view('admin.ops_audit.nursing', compact('wards', 'hmos', 'hmoSchemes'));
    }

    public function data(Request $request, $tab)
    {
        if ($request->isMethod('post') && in_array($request->action, ['bulk_stamp_preview', 'bulk_stamp'])) {
            $modelMap = [
                'admissions' => AdmissionRequest::class,
                'notes' => NursingNote::class,
                'bills' => ProductOrServiceRequest::class,
            ];
            $request->merge(['zone_key' => 'ops_audit.nursing.' . $tab]);
            return $this->handleBulkStamp($request, $tab, $modelMap);
        }

        switch ($tab) {
            case 'admissions':
                return $this->admissionsData($request);
            case 'notes':
                return $this->notesData($request);
            case 'bills':
                return $this->billsData($request);
            default:
                return response()->json(['error' => 'Invalid tab'], 400);
        }
    }

    /**
     * Tab 1: Active Admissions
     */
    protected function admissionsData(Request $request)
    {
        $query = AdmissionRequest::with([
            'patient.user',
            'patient.hmo.scheme',
            'doctor',
            'ward',
            'bed',
        
            'productOrServiceRequest.payment.user',
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');

        if ($request->filled('ward_id')) $query->where('ward_id', $request->ward_id);
        if ($request->filled('status')) $query->where('admission_status', $request->status);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));
        if ($request->filled('hmo_scheme_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('hmo_scheme_id', $request->hmo_scheme_id));
        if ($request->filled('gender')) $query->whereHas('patient.user', fn($q) => $q->where('gender', $request->gender));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            
            $statusColors = [
                'pending_checklist' => 'warning text-dark',
                'admitted' => 'primary',
                'discharged' => 'success'
            ];
            $statusBadge = '<span class="badge bg-'.($statusColors[$row->admission_status] ?? 'secondary').'">'.ucfirst(str_replace('_', ' ', $row->admission_status ?? '')).'</span>';
            $los = $row->admitted_at ? Carbon::parse($row->admitted_at)->diffInDays($row->discharged_at ? Carbon::parse($row->discharged_at) : now()) : '-';

            $bills = ProductOrServiceRequest::where('admission_request_id', $row->id)->with('payment')->get();
            $totalAmount = $bills->sum('amount');
            $totalPayable = $bills->sum('payable_amount');
            $totalClaims = $bills->sum('claims_amount');
            
            $paymentMethod = '-';
            $cashier = '-';
            $payStatus = '<span class="badge bg-secondary">N/A</span>';
            
            if ($bills->count() > 0) {
                $paidBills = $bills->filter(fn($b) => $b->payment_id != null);
                if ($paidBills->count() == $bills->count()) {
                    $payStatus = '<span class="badge bg-success">Paid</span>';
                    $payment = $paidBills->first()->payment;
                    $paymentMethod = $payment?->payment_method ? '<span class="badge bg-light text-dark border">'.$payment->payment_method.'</span>' : '-';
                    $cashier = $payment?->staff_user?->firstname ? ($payment->staff_user->firstname . ' ' . ($payment->staff_user->surname ?? '')) : '-';
                } elseif ($paidBills->count() > 0) {
                    $payStatus = '<span class="badge bg-info">Partially Paid</span>';
                } else {
                    $payStatus = '<span class="badge bg-warning text-dark">Unpaid</span>';
                }
            }

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'ward' => $row->ward?->name ?? '-',
                'bed' => $row->bed?->name ?? '-',
                'status' => $statusBadge,
                'los' => $los !== '-' ? $los . ' days' : '-',
                'total_bill' => $totalAmount > 0 ? '₦' . number_format($totalAmount, 2) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'AdmissionRequest'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Active Admissions', 'value' => number_format($all->where('admission_status', 'admitted')->count()), 'color' => '#198754'],
                ['label' => 'Discharged', 'value' => number_format($all->where('admission_status', 'discharged')->count()), 'color' => '#6c757d'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 2: Nursing Notes
     */
    protected function notesData(Request $request)
    {
        $query = NursingNote::with([
            'patient.user',
            'patient.hmo.scheme',
            'createdBy',
            'type',
        
            
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);

        if ($request->filled('completed')) $query->where('completed', $request->completed);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y H:i') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'type' => $row->type?->name ?? '-',
                'author' => $row->createdBy?->firstname ? ($row->createdBy->firstname . ' ' . ($row->createdBy->surname ?? '')) : '-',
                'status' => $row->status ?? '-',
                'completed' => $row->completed ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning text-dark">No</span>',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'NursingNote'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            return [
                ['label' => 'Total Notes', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Completed', 'value' => number_format($all->where('completed', 1)->count()), 'color' => '#198754'],
                ['label' => 'Pending', 'value' => number_format($all->where('completed', 0)->count()), 'color' => '#ffc107'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 3: Ward Bills
     */
    protected function billsData(Request $request)
    {
        // Bills created by nurses or attached to an admission
        $query = ProductOrServiceRequest::with([
            'patient.user',
            'patient.hmo.scheme',
            'staff',
            'payment.user',
            'product',
            'service'
        ])->where(function($q) {
            $q->whereNotNull('admission_request_id')
              ->orWhereHas('staff', function($q2) {
                  $q2->whereHas('roles', fn($r) => $r->where('name', 'NURSE'));
              });
        });

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);

        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $payment = $row->payment;

            $itemName = '-';
            if ($row->type === 'product' && $row->product) $itemName = $row->product->product_name;
            if ($row->type === 'service' && $row->service) $itemName = $row->service->service_name;

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'item' => $itemName,
                'qty' => $row->qty ?? '-',
                'amount' => '₦' . number_format($row->amount ?? 0, 2),
                'payable' => '₦' . number_format($row->payable_amount ?? 0, 2),
                'claims' => '₦' . number_format($row->claims_amount ?? 0, 2),
                'billed_by' => $row->staff?->firstname ? ($row->staff->firstname . ' ' . ($row->staff->surname ?? '')) : '-',
                'cashier' => $payment?->staff_user?->firstname ? ($payment->staff_user->firstname . ' ' . ($payment->staff_user->surname ?? '')) : '-',
                'method' => $payment?->payment_method ? '<span class="badge bg-light text-dark border">' . $payment->payment_method . '</span>' : '-',
                'pay_status' => $payment ? '<span class="badge bg-success">Paid</span>' : '<span class="badge bg-warning text-dark">Unpaid</span>',
                'payment_info' => $this->renderPaymentInfo($row),
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
}
