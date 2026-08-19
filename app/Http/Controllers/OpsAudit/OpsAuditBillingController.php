<?php

namespace App\Http\Controllers\OpsAudit;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Payment;
use App\Models\OrganizationBill;
use App\Models\StaffBill;
use App\Models\AuditMark;

class OpsAuditBillingController extends OpsAuditBaseController
{
    public function index(Request $request)
    {
        $hmos = \App\Models\Hmo::with('scheme')->orderBy('name')->get()->groupBy(fn($hmo) => $hmo->scheme ? $hmo->scheme->name : 'Other Schemes');
        $hmoSchemes = \App\Models\HmoScheme::orderBy('name')->pluck('name', 'id');
        $users = \App\Models\User::role(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER'])->orderBy('firstname')->get()->mapWithKeys(fn($u) => [$u->id => trim($u->firstname . ' ' . ($u->othername ?? '') . ' ' . $u->surname)]);
        $organizations = \App\Models\Organization::orderBy('name')->pluck('name', 'id');
        $banks = \App\Models\Bank::orderBy('name')->pluck('name', 'id');

        return view('admin.ops_audit.billing', compact('hmos', 'hmoSchemes', 'users', 'organizations', 'banks'));
    }

    public function data(Request $request, $tab)
    {
        if ($request->isMethod('post') && in_array($request->action, ['bulk_stamp_preview', 'bulk_stamp'])) {
            return $this->handleBulkStamp($request, $tab);
        }

        switch ($tab) {
            case 'payments':
                return $this->paymentsData($request);
            case 'organization_bills':
                return $this->orgBillsData($request);
            case 'staff_bills':
                return $this->staffBillsData($request);
            default:
                return response()->json(['error' => 'Invalid tab'], 400);
        }
    }

    protected function paymentsData(Request $request)
    {
        $query = Payment::with([
            'patient.user',
            'patient.hmo.scheme',
            'user', // cashier
            'bank',
            'organizationBill.organization',
            'staffBill.staffUser',
            'patientAccount' // if it exists
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);

        $this->applyPaymentFilters($query, $request, 'self_payment');

        if ($request->filled('payment_type')) $query->where('payment_type', $request->payment_type);
        if ($request->filled('hmo_id')) $query->where('hmo_id', $request->hmo_id);
        if ($request->filled('is_audited')) $query->where('is_audited', $request->is_audited);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $cashier = $row->user;

            $entity = $this->renderPaymentEntityDetails($row);
            
            $balance = '-';
            if ($row->payment_method === 'BILL_TO_STAFF' && $row->staffBill) {
                $balance = '₦' . number_format($row->staffBill->outstanding_amount ?? 0, 2);
            } elseif ($row->payment_method === 'BILL_TO_ORG' && $row->organizationBill) {
                $balance = '₦' . number_format($row->organizationBill->outstanding_amount ?? 0, 2);
            } elseif ($row->payment_method === 'ACCOUNT') {
                $balance = $row->patientAccount ? '₦' . number_format($row->patientAccount->balance ?? 0, 2) : '?';
            }

            $bankHtml = $this->renderBankDetails($row);

            return [
                'date_time' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y H:i') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'ref_no' => $row->reference_no ?? '-',
                'total' => '₦' . number_format($row->total ?? 0, 2),
                'discount' => '₦' . number_format($row->total_discount ?? 0, 2),
                'method' => '<span class="badge bg-light text-dark border">' . ucfirst(str_replace('_', ' ', $row->payment_method ?? '-')) . '</span>',
                'type' => ucfirst(str_replace('_', ' ', $row->payment_type ?? '-')),
                'bank' => $bankHtml,
                'entity' => $entity,
                'balance' => $balance,
                'cashier' => $cashier?->firstname ? ($cashier->firstname . ' ' . ($cashier->surname ?? '')) : '-',
                'shift' => $row->shift?->name ?? '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'Payment'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Collected', 'value' => '₦' . number_format((clone $kpiQuery)->sum('total'), 2), 'color' => '#0d6efd'],
                ['label' => 'Cash', 'value' => '₦' . number_format((clone $kpiQuery)->where('payment_method', 'CASH')->sum('total'), 2), 'color' => '#198754'],
                ['label' => 'POS', 'value' => '₦' . number_format((clone $kpiQuery)->where('payment_method', 'POS')->sum('total'), 2), 'color' => '#0dcaf0'],
                ['label' => 'Transfer', 'value' => '₦' . number_format((clone $kpiQuery)->where('payment_method', 'TRANSFER')->sum('total'), 2), 'color' => '#6610f2'],
                ['label' => 'HMO Full Cover', 'value' => '₦' . number_format((clone $kpiQuery)->where('payment_method', 'HMO_FULL_COVER')->sum('total'), 2), 'color' => '#dc3545'],
                ['label' => 'Refunds', 'value' => '₦' . number_format((clone $kpiQuery)->where('payment_method', 'REFUND')->sum('total'), 2), 'color' => '#ffc107'],
                ['label' => 'Total Discounts', 'value' => '₦' . number_format((clone $kpiQuery)->sum('total_discount'), 2), 'color' => '#6c757d'],
            ];
        }, $kpiQuery);
    }

    protected function orgBillsData(Request $request)
    {
        $query = OrganizationBill::with([
            'patient.user',
            'patient.hmo.scheme',
            'organization',
            'payment.user',
            'settlementPayment'
        ]);

        $this->applyDateFilter($query, $request);

        if ($request->filled('organization_id')) $query->where('organization_id', $request->organization_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('is_audited')) $query->where('is_audited', $request->is_audited);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $cashier = $row->payment?->user;
            
            $statusColors = ['pending' => 'warning text-dark', 'pending_audit' => 'secondary', 'paid' => 'success', 'rejected' => 'danger'];
            $sColor = $statusColors[$row->status] ?? 'secondary';

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'organization' => $row->organization?->name ?? '-',
                'total' => '₦' . number_format($row->total_amount ?? 0, 2),
                'discount' => '₦' . number_format($row->discount_amount ?? 0, 2),
                'outstanding' => '₦' . number_format($row->outstanding_amount ?? 0, 2),
                'status' => '<span class="badge bg-' . $sColor . '">' . ucfirst(str_replace('_', ' ', $row->status ?? '-')) . '</span>',
                'settlement_by' => $row->settlementPayment ? 'Payment #' . $row->settlementPayment->reference_no : '-',
                'settled_at' => $row->settled_at ? Carbon::parse($row->settled_at)->format('d M Y') : '-',
                'audited' => $row->is_audited ? '<i class="mdi mdi-check text-success"></i> Yes' : '<i class="mdi mdi-close text-danger"></i> No',
                'cashier' => $cashier?->firstname ? ($cashier->firstname . ' ' . ($cashier->surname ?? '')) : '-',
                'method' => $row->payment?->payment_method ?? '-',
                'pay_status' => $row->status === 'paid' ? '<span class="badge bg-success">Paid</span>' : '<span class="badge bg-warning text-dark">Unpaid</span>',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'OrganizationBill'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Bills', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Pending Audit', 'value' => number_format((clone $kpiQuery)->where('status', 'pending_audit')->count()), 'color' => '#6c757d'],
                ['label' => 'Paid', 'value' => number_format((clone $kpiQuery)->where('status', 'paid')->count()), 'color' => '#198754'],
                ['label' => 'Pending', 'value' => number_format((clone $kpiQuery)->where('status', 'pending')->count()), 'color' => '#ffc107'],
                ['label' => 'Outstanding Value', 'value' => '₦' . number_format((clone $kpiQuery)->sum('outstanding_amount'), 2), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    protected function staffBillsData(Request $request)
    {
        $query = StaffBill::with([
            'patient.user',
            'patient.hmo.scheme',
            'staffUser',
            'checkoutPayment.user',
            'settlementPayment'
        ]);

        $this->applyDateFilter($query, $request);

        if ($request->filled('staff_user_id')) $query->where('staff_user_id', $request->staff_user_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('cashier_id')) $query->whereHas('checkoutPayment', fn($q) => $q->where('user_id', $request->cashier_id));
        if ($request->filled('is_audited')) $query->where('is_audited', $request->is_audited);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $cashier = $row->checkoutPayment?->user;
            $staff = $row->staffUser;

            $statusColors = ['pending' => 'warning text-dark', 'paid' => 'success', 'rejected' => 'danger'];
            $sColor = $statusColors[$row->status] ?? 'secondary';

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'staff_member' => $staff?->firstname ? ($staff->firstname . ' ' . ($staff->surname ?? '')) : '-',
                'total' => '₦' . number_format($row->total_amount ?? 0, 2),
                'discount' => '₦' . number_format($row->discount_amount ?? 0, 2),
                'outstanding' => '₦' . number_format($row->outstanding_amount ?? 0, 2),
                'status' => '<span class="badge bg-' . $sColor . '">' . ucfirst($row->status ?? '-') . '</span>',
                'settlement_payment' => $row->settlementPayment ? 'Payment #' . $row->settlementPayment->reference_no : '-',
                'settled_at' => $row->settled_at ? Carbon::parse($row->settled_at)->format('d M Y') : '-',
                'audited' => $row->is_audited ? '<i class="mdi mdi-check text-success"></i> Yes' : '<i class="mdi mdi-close text-danger"></i> No',
                'cashier' => $cashier?->firstname ? ($cashier->firstname . ' ' . ($cashier->surname ?? '')) : '-',
                'pay_status' => $row->status === 'paid' ? '<span class="badge bg-success">Paid</span>' : '<span class="badge bg-warning text-dark">Unpaid</span>',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'StaffBill'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Bills', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Paid', 'value' => number_format((clone $kpiQuery)->where('status', 'paid')->count()), 'color' => '#198754'],
                ['label' => 'Pending', 'value' => number_format((clone $kpiQuery)->where('status', 'pending')->count()), 'color' => '#ffc107'],
                ['label' => 'Outstanding Value', 'value' => '₦' . number_format((clone $kpiQuery)->sum('outstanding_amount'), 2), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    protected function handleBulkStamp(Request $request, $tab)
    {
        $modelMap = [
            'payments' => Payment::class,
            'organization_bills' => OrganizationBill::class,
            'staff_bills' => StaffBill::class,
        ];
        
        $request->merge(['zone_key' => 'ops_audit.billing.' . $tab]);
        return $this->processBulkStamp($request, $tab, $modelMap);
    }
}
