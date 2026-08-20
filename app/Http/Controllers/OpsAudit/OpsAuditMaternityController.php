<?php

namespace App\Http\Controllers\OpsAudit;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\MaternityEnrollment;
use App\Models\AncVisit;
use App\Models\DeliveryRecord;
use App\Models\MaternityBaby;
use App\Models\PostnatalVisit;
use App\Models\ImmunizationRecord;
use App\Models\ProductOrServiceRequest;

class OpsAuditMaternityController extends OpsAuditBaseController
{
    public function index(Request $request)
    {
        $hmos = \App\Models\Hmo::with('scheme')->orderBy('name')->get()->groupBy(fn($hmo) => $hmo->scheme ? $hmo->scheme->name : 'Other Schemes');
        $hmoSchemes = \App\Models\HmoScheme::orderBy('name')->pluck('name', 'id');

        return view('admin.ops_audit.maternity', compact('hmos', 'hmoSchemes'));
    }

    public function data(Request $request, $tab)
    {
        if ($request->isMethod('post') && in_array($request->action, ['bulk_stamp_preview', 'bulk_stamp'])) {
            $modelMap = [
                'enrollments' => MaternityEnrollment::class,
                'anc' => AncVisit::class,
                'deliveries' => DeliveryRecord::class,
                'babies' => MaternityBaby::class,
                'postnatal' => PostnatalVisit::class,
                'immunizations' => ImmunizationRecord::class,
                'bills' => ProductOrServiceRequest::class,
            ];
            $request->merge(['zone_key' => 'ops_audit.maternity.' . $tab]);
            return $this->handleBulkStamp($request, $tab, $modelMap);
        }

        switch ($tab) {
            case 'enrollments':
                return $this->enrollmentsData($request);
            case 'anc':
                return $this->ancData($request);
            case 'deliveries':
                return $this->deliveriesData($request);
            case 'babies':
                return $this->babiesData($request);
            case 'postnatal':
                return $this->postnatalData($request);
            case 'immunizations':
                return $this->immunizationsData($request);
            case 'bills':
                return $this->billsData($request);
            default:
                return response()->json(['error' => 'Invalid tab'], 400);
        }
    }

    /**
     * Tab 1: Enrollments
     */
    protected function enrollmentsData(Request $request)
    {
        $query = MaternityEnrollment::with([
'patient.user',
            'patient.hmo.scheme',
        
            'serviceRequest.payment.user',
]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'serviceRequest');
        $this->applyItemFilters($query, $request, 'serviceRequest');

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, function($q) {
            return $q->withCount(['ancVisits', 'postnatalVisits', 'babies']);
        }, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;

            $statusBadge = '<span class="badge bg-'.($row->status === 'completed' ? 'success' : 'primary').'">'.ucfirst($row->status ?? 'Active').'</span>';

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'lmp' => $row->lmp ? Carbon::parse($row->lmp)->format('d M Y') : '-',
                'edd' => $row->edd ? Carbon::parse($row->edd)->format('d M Y') : '-',
                'gravida_parity' => 'G' . ($row->gravida ?? '-') . ' P' . ($row->parity ?? '-'),
                'status' => $statusBadge,
                'anc_count' => $row->anc_visits_count > 0 ? '<span class="badge bg-info">'.$row->anc_visits_count.'</span>' : '-',
                'postnatal_count' => $row->postnatal_visits_count > 0 ? '<span class="badge bg-secondary">'.$row->postnatal_visits_count.'</span>' : '-',
                'babies_count' => $row->babies_count > 0 ? '<span class="badge bg-danger">'.$row->babies_count.'</span>' : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'MaternityEnrollment'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Enrollments', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Active/Postnatal', 'value' => number_format((clone $kpiQuery)->where('status', '!=', 'completed')->count()), 'color' => '#198754'],
                ['label' => 'Completed', 'value' => number_format((clone $kpiQuery)->where('status', 'completed')->count()), 'color' => '#6c757d'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 2: ANC Visits
     */
    protected function ancData(Request $request)
    {
        $query = AncVisit::with([
'patient.user',
            'patient.hmo.scheme',
        
            
        
            'encounter.productOrServiceRequest.payment.user',
]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'encounter.productOrServiceRequest');
        $this->applyItemFilters($query, $request, 'encounter.productOrServiceRequest');

        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'visit_no' => $row->visit_number ?? '-',
                'ga' => ($row->gestational_age_weeks ?? '-') . ' wks ' . ($row->gestational_age_days ?? '0') . ' days',
                'bp' => $row->blood_pressure_systolic ? ($row->blood_pressure_systolic . '/' . $row->blood_pressure_diastolic) : '-',
                'weight' => $row->weight_kg ? $row->weight_kg . ' kg' : '-',
                'fhr' => $row->fetal_heart_rate ?? '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'AncVisit'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total ANC Visits', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Unique Patients', 'value' => number_format((clone $kpiQuery)->distinct()->count('patient_id')), 'color' => '#198754'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 3: Deliveries
     */
    protected function deliveriesData(Request $request)
    {
        $query = DeliveryRecord::with([
'patient.user',
            'patient.hmo.scheme',
        
            'encounter.productOrServiceRequest.payment.user',
]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'encounter.productOrServiceRequest');
        $this->applyItemFilters($query, $request, 'encounter.productOrServiceRequest');

        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'type' => ucfirst(str_replace('_', ' ', $row->type_of_delivery ?? '-')),
                'place' => $row->place_of_delivery ?? '-',
                'babies' => $row->number_of_babies ?? '-',
                'blood_loss' => $row->blood_loss_ml ? $row->blood_loss_ml . ' ml' : '-',
                'delivered_by' => $row->delivered_by ?? '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'DeliveryRecord'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Deliveries', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Total Babies', 'value' => number_format((clone $kpiQuery)->sum('number_of_babies')), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 4: Baby Records
     */
    protected function babiesData(Request $request)
    {
        $query = MaternityBaby::with([
'patient.user', // Mother
            'patient.hmo.scheme',
]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'enrollment.serviceRequest');
        $this->applyItemFilters($query, $request, 'enrollment.serviceRequest');

        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $mother = $row->patient;
            $user = $mother?->user;
            $hmo = $mother?->hmo;

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'mother' => $this->renderPatient($user, $mother, $hmo),
                'sex' => ucfirst($row->sex ?? '-'),
                'weight' => $row->birth_weight_kg ? $row->birth_weight_kg . ' kg' : '-',
                'apgar' => $row->apgar_1_min ? $row->apgar_1_min . '/' . ($row->apgar_5_min ?? '-') : '-',
                'status' => '<span class="badge bg-'.($row->status === 'alive' ? 'success' : 'danger').'">'.ucfirst($row->status ?? '-').'</span>',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'MaternityBaby'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Babies', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Alive', 'value' => number_format((clone $kpiQuery)->where('status', 'alive')->count()), 'color' => '#198754'],
                ['label' => 'Stillbirths/Deceased', 'value' => number_format((clone $kpiQuery)->where('status', '!=', 'alive')->count()), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 5: Postnatal Visits
     */
    protected function postnatalData(Request $request)
    {
        $query = PostnatalVisit::with([
'patient.user',
            'patient.hmo.scheme',
        
            'encounter.productOrServiceRequest.payment.user',
]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'encounter.productOrServiceRequest');
        $this->applyItemFilters($query, $request, 'encounter.productOrServiceRequest');

        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'days_pp' => $row->days_postpartum ?? '-',
                'bp' => $row->blood_pressure ?? '-',
                'condition' => $row->general_condition ?? '-',
                'baby_weight' => $row->baby_weight_kg ? $row->baby_weight_kg . ' kg' : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'PostnatalVisit'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Visits', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 6: Immunizations
     */
    protected function immunizationsData(Request $request)
    {
        $query = ImmunizationRecord::with([
'patient.user',
            'patient.hmo.scheme',
            'product.category',
        
            'productOrServiceRequest.payment.user',
]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');
        $this->applyItemFilters($query, $request, 'productOrServiceRequest');

        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'vaccine' => $row->vaccine_name ?? $row->product?->product_name ?? '-',
                'dose' => $row->dose_number ?? '-',
                'route' => $row->route ?? '-',
                'administered_at' => $row->administered_at ? Carbon::parse($row->administered_at)->format('d M Y') : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'ImmunizationRecord'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Vaccines Given', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 7: Maternity Bills
     */
    protected function billsData(Request $request)
    {
        // Get all bills where patient is in maternity enrollments
        $query = ProductOrServiceRequest::with([
'patient.user',
            'patient.hmo.scheme',
            'staff',
            'payment.staff_user',
])->whereHas('patient', function($q) {
            $q->whereHas('maternityEnrollments');
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

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
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
            return [
                ['label' => 'Total Bills', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Total Amount', 'value' => '₦' . number_format((clone $kpiQuery)->sum('amount'), 2), 'color' => '#6610f2'],
                ['label' => 'Payable', 'value' => '₦' . number_format((clone $kpiQuery)->sum('payable_amount'), 2), 'color' => '#198754'],
                ['label' => 'Claims', 'value' => '₦' . number_format((clone $kpiQuery)->sum('claims_amount'), 2), 'color' => '#0dcaf0'],
            ];
        }, $kpiQuery);
    }
}
