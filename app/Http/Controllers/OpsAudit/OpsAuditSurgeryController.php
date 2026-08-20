<?php

namespace App\Http\Controllers\OpsAudit;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Procedure;
use App\Models\ProductOrServiceRequest;
use App\Models\Payment;

class OpsAuditSurgeryController extends OpsAuditBaseController
{
    public function index(Request $request)
    {
        $hmos = \App\Models\Hmo::with('scheme')->orderBy('name')->get()->groupBy(fn($hmo) => $hmo->scheme ? $hmo->scheme->name : 'Other Schemes');
        $hmoSchemes = \App\Models\HmoScheme::orderBy('name')->pluck('name', 'id');
        $cashiers = \App\Models\User::role(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER'])->orderBy('firstname')->get()->mapWithKeys(fn($u) => [$u->id => trim($u->firstname . ' ' . ($u->othername ?? '') . ' ' . $u->surname)]);
        $stores = $this->getPermittedStoresForFilter(['roles' => ['department'], 'name_match' => ['%surg%', '%theat%', '%op%', '%operating%']]);

        return view('admin.ops_audit.surgery', compact('hmos', 'hmoSchemes', 'cashiers', 'stores'));
    }

    public function data(Request $request, $tab)
    {
        if ($request->isMethod('post') && in_array($request->action, ['bulk_stamp_preview', 'bulk_stamp'])) {
            $modelMap = [
                'procedures' => Procedure::class,
                'notes' => Procedure::class,
                'bills' => ProductOrServiceRequest::class,
                'requisitions' => \App\Models\StoreRequisition::class,
                'cashbook' => Payment::class,
            ];
            $request->merge(['zone_key' => 'ops_audit.surgery.' . $tab]);
            return $this->handleBulkStamp($request, $tab, $modelMap);
        }

        switch ($tab) {
            case 'procedures':
                return $this->proceduresData($request);
            case 'notes':
                return $this->notesData($request);
            case 'bills':
                return $this->billsData($request);
            case 'requisitions':
                return $this->moduleRequisitionsData($request, [
                    'roles' => ['department'], 
                    'name_match' => ['%surg%', '%theat%', '%op%', '%operating%']
                ]);
            case 'cashbook':
                return $this->cashbookData($request);
            default:
                return response()->json(['error' => 'Invalid tab'], 400);
        }
    }

    /**
     * Tab 1: Procedures
     */
    protected function proceduresData(Request $request)
    {
        $query = Procedure::with([
'patient.user',
            'patient.hmo.scheme',
            'requestedByUser',
            'service.category',
            'productOrServiceRequest.payment.staff_user',
        
            'productOrServiceRequest.payment.user',
]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');
        $this->applyItemFilters($query, $request, 'productOrServiceRequest');

        if ($request->filled('status')) $query->where('procedure_status', $request->status);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $posr = $row->productOrServiceRequest;
            $payment = $posr?->payment;

            $statusColors = ['requested' => 'warning text-dark', 'in_progress' => 'info', 'completed' => 'success', 'cancelled' => 'danger'];
            
            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'procedure' => $this->renderItemDetails($row),
                'doctor' => $row->requestedByUser?->firstname ? ($row->requestedByUser->firstname . ' ' . ($row->requestedByUser->surname ?? '')) : '-',
                'status' => '<span class="badge bg-'.($statusColors[$row->procedure_status] ?? 'secondary').'">'.ucfirst(str_replace('_', ' ', $row->procedure_status ?? '-')).'</span>',
                'consent' => $row->consent_status ? '<span class="badge bg-success">Signed</span>' : '<span class="badge bg-warning text-dark">Pending</span>',
                'outcome' => ucfirst($row->outcome ?? '-'),
                'or' => $row->operating_room ?? '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'Procedure'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Procedures', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Requested', 'value' => number_format((clone $kpiQuery)->where('procedure_status', 'requested')->count()), 'color' => '#ffc107'],
                ['label' => 'Completed', 'value' => number_format((clone $kpiQuery)->where('procedure_status', 'completed')->count()), 'color' => '#198754'],
                ['label' => 'Consent Pending', 'value' => number_format((clone $kpiQuery)->where('consent_status', 0)->count()), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 2: Pre & Post Notes
     */
    protected function notesData(Request $request)
    {
        $query = Procedure::with([
'patient.user',
            'service.category',
            'preNotesBy',
            'postNotesBy',
        
            'productOrServiceRequest.payment.user',
]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');
        $this->applyItemFilters($query, $request, 'productOrServiceRequest');

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, null),
                'procedure' => $this->renderItemDetails($row),
                'pre_notes' => $row->pre_notes ? '<i class="mdi mdi-check text-success"></i> Yes' : '<i class="mdi mdi-close text-danger"></i> No',
                'pre_by' => $row->preNotesBy?->firstname ? ($row->preNotesBy->firstname . ' ' . ($row->preNotesBy->surname ?? '')) : '-',
                'post_notes' => $row->post_notes ? '<i class="mdi mdi-check text-success"></i> Yes' : '<i class="mdi mdi-close text-danger"></i> No',
                'post_by' => $row->postNotesBy?->firstname ? ($row->postNotesBy->firstname . ' ' . ($row->postNotesBy->surname ?? '')) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'Procedure'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Procedures', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Pre-Notes Done', 'value' => number_format((clone $kpiQuery)->whereNotNull('pre_notes')->count()), 'color' => '#198754'],
                ['label' => 'Post-Notes Done', 'value' => number_format((clone $kpiQuery)->whereNotNull('post_notes')->count()), 'color' => '#0dcaf0'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 3: Bills
     */
    protected function billsData(Request $request)
    {
        $query = ProductOrServiceRequest::with([
'patient.user',
            'patient.hmo.scheme',
            'staff',
            'payment.staff_user',
            'procedure'
])->whereHas('procedure');

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, '');
        $this->applyItemFilters($query, $request, '');

        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $payment = $row->payment;
            $procedure = $row->procedure;

            $procName = $procedure ? ($procedure->is_free_form ? $procedure->free_form_name : ($procedure->service?->service_name ?? '-')) : '-';

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'procedure' => $procName,
                'amount' => '₦' . number_format($row->amount ?? 0, 2),
                'payment_info' => $this->renderPaymentInfo($row),
                                'billed_by' => $row->staff?->firstname ? ($row->staff->firstname . ' ' . ($row->staff->surname ?? '')) : '-',
                'audit' => $this->renderAuditAction($row, 'ProductOrServiceRequest'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Bills', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Total Amount', 'value' => '₦' . number_format((clone $kpiQuery)->sum('amount'), 2), 'color' => '#6610f2'],
                ['label' => 'Payable', 'value' => '₦' . number_format((clone $kpiQuery)->sum('payable_amount'), 2), 'color' => '#198754'],
                ['label' => 'Claims', 'value' => '₦' . number_format((clone $kpiQuery)->sum('claims_amount'), 2), 'color' => '#0dcaf0'],
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
            'product_or_service_request', 'product_or_service_request.product.category', 'product_or_service_request.service.category'
])->whereHas('product_or_service_request.procedure');

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'self_payment');
        $this->applyItemFilters($query, $request, 'product_or_service_request');

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            
            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y H:i') : '-',
                'reference' => $row->reference_no ?? '-',
                'item' => $this->renderPosrItem($row->product_or_service_request, $row->id),
                'patient' => $this->renderPatient($user, $patient, null),
                'total' => '₦' . number_format($row->total ?? 0, 2),
                'method' => $row->payment_method ? '<span class="badge bg-light text-dark border">'.$row->payment_method.'</span>' : '-',
                'cashier' => $row->staff_user?->firstname ? ($row->staff_user->firstname . ' ' . ($row->staff_user->surname ?? '')) : '-',
                'bank' => $this->renderBankDetails($row),
                'entity' => $this->renderPaymentEntityDetails($row),
                'audit' => $this->renderAuditAction($row, 'Payment'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Transactions', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Total Revenue', 'value' => '₦' . number_format((clone $kpiQuery)->sum('total'), 2), 'color' => '#198754'],
            ];
        }, $kpiQuery);
    }
}
