<?php

namespace App\Http\Controllers\OpsAudit;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Encounter;
use App\Models\AdmissionRequest;
use App\Models\ProductRequest;
use App\Models\LabServiceRequest;
use App\Models\ImagingServiceRequest;
use App\Models\Procedure;
use App\Models\SpecialistReferral;

class OpsAuditDoctorController extends OpsAuditBaseController
{
    /**
     * Show the doctor audit page.
     */
    public function index(Request $request)
    {
        $clinics = \App\Models\Clinic::orderBy('name')->pluck('name', 'id');
        $hmos = \App\Models\Hmo::with('scheme')->orderBy('name')->get()->groupBy(fn($hmo) => $hmo->scheme ? $hmo->scheme->name : 'Other Schemes');
        $hmoSchemes = \App\Models\HmoScheme::orderBy('name')->pluck('name', 'id');
        $doctors = \App\Models\User::role('DOCTOR')->orderBy('firstname')->get()->mapWithKeys(fn($u) => [$u->id => trim($u->firstname . ' ' . ($u->othername ?? '') . ' ' . $u->surname)]);

        return view('admin.ops_audit.doctor', compact('clinics', 'hmos', 'hmoSchemes', 'doctors'));
    }

    /**
     * DataTable AJAX endpoint for a specific tab.
     */
    public function data(Request $request, $tab)
    {
        // Handle bulk stamp actions (POST)
        if ($request->isMethod('post') && in_array($request->action, ['bulk_stamp_preview', 'bulk_stamp'])) {
            $modelMap = [
                'encounters' => Encounter::class,
                'admissions' => AdmissionRequest::class,
                'prescriptions' => ProductRequest::class,
                'labs' => LabServiceRequest::class,
                'imaging' => ImagingServiceRequest::class,
                'procedures' => Procedure::class,
                'referrals' => SpecialistReferral::class,
            ];
            $request->merge(['zone_key' => 'ops_audit.doctor.' . $tab]);
            return $this->handleBulkStamp($request, $tab, $modelMap);
        }

        switch ($tab) {
            case 'encounters':
                return $this->encountersData($request);
            case 'admissions':
                return $this->admissionsData($request);
            case 'prescriptions':
                return $this->prescriptionsData($request);
            case 'labs':
                return $this->labsData($request);
            case 'imaging':
                return $this->imagingData($request);
            case 'procedures':
                return $this->proceduresData($request);
            case 'referrals':
                return $this->referralsData($request);
            default:
                return response()->json(['error' => 'Invalid tab'], 400);
        }
    }

    /**
     * Tab 1: Encounters
     */
    protected function encountersData(Request $request)
    {
        $query = Encounter::with([
            'patient.user',
            'patient.hmo.scheme',
            'doctor',
            'queue.clinic',
        
            'productOrServiceRequest.payment.user',
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');

        if ($request->filled('doctor_id')) $query->where('doctor_id', $request->doctor_id);
        if ($request->filled('clinic_id')) $query->whereHas('queue', fn($q) => $q->where('clinic_id', $request->clinic_id));
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));
        if ($request->filled('hmo_scheme_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('hmo_scheme_id', $request->hmo_scheme_id));
        if ($request->filled('gender')) $query->whereHas('patient.user', fn($q) => $q->where('gender', $request->gender));
        if ($request->filled('completed')) $query->where('completed', $request->completed);
        if ($request->filled('outcome')) $query->where('outcome', $request->outcome);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, function ($q) {
            // Count related items for each encounter
            return $q->withCount([
                'productRequests as rx_count',
                'labRequests as lab_count',
                'imagingRequests as imaging_count',
                'admissionRequests as adm_count',
                'procedures as proc_count',
                'referrals as ref_count'
            ]);
        }, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $doctor = $row->doctor;

            $status = $row->completed ? ['Completed', 'success'] : ['Ongoing', 'warning text-dark'];
            $duration = $row->started_at && $row->completed_at 
                ? Carbon::parse($row->started_at)->diffInMinutes(Carbon::parse($row->completed_at)) 
                : '-';

            $admCount = $row->adm_count ?? 0;
            $procCount = $row->proc_count ?? 0;
            $refCount = $row->ref_count ?? 0;

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y H:i') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'doctor' => $doctor?->firstname ? ($doctor->firstname . ' ' . ($doctor->surname ?? '')) : '<span class="text-muted">-</span>',
                'clinic' => $row->clinic?->name ?? '<span class="text-muted">-</span>',
                'duration' => $duration !== '-' ? $duration . ' min' : '-',
                'completed' => '<span class="badge bg-' . $status[1] . '">' . $status[0] . '</span>',
                'outcome' => $row->outcome ? ucfirst($row->outcome) : '-',
                'rx' => $row->rx_count > 0 ? '<span class="badge bg-primary">'.$row->rx_count.'</span>' : '-',
                'labs' => $row->lab_count > 0 ? '<span class="badge bg-info">'.$row->lab_count.'</span>' : '-',
                'imaging' => $row->imaging_count > 0 ? '<span class="badge bg-secondary">'.$row->imaging_count.'</span>' : '-',
                'admissions' => $admCount > 0 ? '<span class="badge bg-danger">'.$admCount.'</span>' : '-',
                'procedures' => $procCount > 0 ? '<span class="badge bg-warning text-dark">'.$procCount.'</span>' : '-',
                'referrals' => $refCount > 0 ? '<span class="badge bg-dark">'.$refCount.'</span>' : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'Encounter'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Encounters', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Completed', 'value' => number_format((clone $kpiQuery)->where('completed', 1)->count()), 'color' => '#198754'],
                ['label' => 'Ongoing', 'value' => number_format((clone $kpiQuery)->where('completed', 0)->count()), 'color' => '#ffc107'],
                ['label' => 'Avg Duration', 'value' => (clone $kpiQuery)->where('completed', 1)->count() > 0 
                    ? round((clone $kpiQuery)->where('completed', 1)->avg(\Illuminate\Support\Facades\DB::raw('TIMESTAMPDIFF(MINUTE, started_at, completed_at)'))) . 'm' 
                    : '-', 'color' => '#6f42c1'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 2: Admissions
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
            'bills.payment'
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');

        if ($request->filled('doctor_id')) $query->where('doctor_id', $request->doctor_id);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));
        if ($request->filled('hmo_scheme_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('hmo_scheme_id', $request->hmo_scheme_id));
        if ($request->filled('gender')) $query->whereHas('patient.user', fn($q) => $q->where('gender', $request->gender));
        if ($request->filled('status')) $query->where('admission_status', $request->status);

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
            $statusText = str_replace('_', ' ', ucfirst($row->admission_status ?? ''));
            $statusBadge = '<span class="badge bg-'.($statusColors[$row->admission_status] ?? 'secondary').'">'.$statusText.'</span>';

            $los = $row->admitted_at ? Carbon::parse($row->admitted_at)->diffInDays($row->discharged_at ? Carbon::parse($row->discharged_at) : now()) : '-';

            // Aggregate bills for this admission request
            // We find all ProductOrServiceRequests linked to this admission
            $bills = $row->bills;
            $totalAmount = $bills->sum('amount');
            $totalPayable = $bills->sum('payable_amount');
            $totalClaims = $bills->sum('claims_amount');
            
            $paymentMethod = '-';
            $cashier = '-';
            $payStatus = '<span class="badge bg-secondary">N/A</span>';
            
            if ($bills->count() > 0) {
                // Determine overall pay status based on whether all payable amount is paid
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
                'doctor' => $row->doctor?->firstname ? ($row->doctor->firstname . ' ' . ($row->doctor->surname ?? '')) : '-',
                'ward' => $row->ward?->name ?? '-',
                'bed' => $row->bed?->name ?? '-',
                'esi' => $row->esi_level ? '<span class="badge bg-danger">Level '.$row->esi_level.'</span>' : '-',
                'status' => $statusBadge,
                'los' => $los !== '-' ? $los . ' days' : '-',
                'total_bill' => $totalAmount > 0 ? '₦' . number_format($totalAmount, 2) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'AdmissionRequest'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Active', 'value' => number_format((clone $kpiQuery)->where('admission_status', 'admitted')->count()), 'color' => '#198754'],
                ['label' => 'Pending Checklist', 'value' => number_format((clone $kpiQuery)->where('admission_status', 'pending_checklist')->count()), 'color' => '#ffc107'],
                ['label' => 'Discharged', 'value' => number_format((clone $kpiQuery)->where('admission_status', 'discharged')->count()), 'color' => '#6c757d'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 3: Prescriptions
     */
    protected function prescriptionsData(Request $request)
    {
        $query = ProductRequest::with([
            'patient.user',
            'patient.hmo.scheme',
            'doctor',
            'product',
            'productOrServiceRequest.payment',
            'productOrServiceRequest.payment.user',
            'biller',
            'dispenser',
            'dispensedFromStore'
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');

        if ($request->filled('doctor_id')) $query->where('doctor_id', $request->doctor_id);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));
        if ($request->filled('status')) $query->where('status', $request->status);

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
            
            $biller = $row->biller ?? $posr?->biller;
            if ($biller) {
                $statusHtml .= '<div class="mt-1 text-muted fw-bold" style="font-size:0.7rem;"><i class="mdi mdi-receipt me-1"></i>Billed: ' . trim($biller->firstname . ' ' . $biller->surname) . '</div>';
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
                'store' => $row->dispensedFromStore ? ($row->dispensedFromStore->store_name . '<br><small class="text-muted">' . $row->dispensedFromStore->distributionRoleLabel() . '</small>') : '-',
                'status' => $statusHtml,
                'billed_by' => $row->biller?->firstname ? ($row->biller->firstname . ' ' . ($row->biller->surname ?? '')) : '-',
                'dispensed_by' => $row->dispenser?->firstname ? ($row->dispenser->firstname . ' ' . ($row->dispenser->surname ?? '')) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'ProductRequest'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Pending', 'value' => number_format((clone $kpiQuery)->where('status', 1)->count()), 'color' => '#ffc107'],
                ['label' => 'Approved', 'value' => number_format((clone $kpiQuery)->where('status', 2)->count()), 'color' => '#0dcaf0'],
                ['label' => 'Dispensed', 'value' => number_format((clone $kpiQuery)->where('status', 3)->count()), 'color' => '#198754'],
                ['label' => 'Returned', 'value' => number_format((clone $kpiQuery)->where('status', 4)->count()), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 4: Lab Requests
     */
    protected function labsData(Request $request)
    {
        $query = LabServiceRequest::with([
            'patient.user',
            'patient.hmo.scheme',
            'doctor',
            'service',
            'biller',
            'resultBy',
            'approver',
            'productOrServiceRequest.payment',
        
            'productOrServiceRequest.payment.user',
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');
        
        if ($request->filled('doctor_id')) $query->where('doctor_id', $request->doctor_id);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));
        if ($request->filled('status')) $query->where('status', $request->status);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $posr = $row->productOrServiceRequest;
            $payment = $posr?->payment;

            $statusColors = [1 => 'warning text-dark', 2 => 'info', 3 => 'primary', 4 => 'success'];
            $statusTexts = [1 => 'Ordered', 2 => 'Sample Collected', 3 => 'Result Entered', 4 => 'Approved'];

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'test' => $row->service?->service_name ?? ($row->is_free_form ? $row->free_form_name : '-'),
                'doctor' => $row->doctor?->firstname ? ($row->doctor->firstname . ' ' . ($row->doctor->surname ?? '')) : '-',
                'status' => '<span class="badge bg-'.($statusColors[$row->status] ?? 'secondary').'">'.($statusTexts[$row->status] ?? $row->status).'</span>',
                'sample_by' => '-', // Sample by logic
                'result_by' => $row->resultBy?->firstname ? ($row->resultBy->firstname . ' ' . ($row->resultBy->surname ?? '')) : '-',
                'approved_by' => $row->approver?->firstname ? ($row->approver->firstname . ' ' . ($row->approver->surname ?? '')) : '-',
                'billed_by' => $row->biller?->firstname ? ($row->biller->firstname . ' ' . ($row->biller->surname ?? '')) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'LabServiceRequest'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Ordered', 'value' => number_format((clone $kpiQuery)->where('status', 1)->count()), 'color' => '#ffc107'],
                ['label' => 'Sample Collected', 'value' => number_format((clone $kpiQuery)->where('status', 2)->count()), 'color' => '#0dcaf0'],
                ['label' => 'Result Entered', 'value' => number_format((clone $kpiQuery)->where('status', 3)->count()), 'color' => '#0d6efd'],
                ['label' => 'Approved', 'value' => number_format((clone $kpiQuery)->where('status', 4)->count()), 'color' => '#198754'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 5: Imaging Requests
     */
    protected function imagingData(Request $request)
    {
        $query = ImagingServiceRequest::with([
            'patient.user',
            'patient.hmo.scheme',
            'doctor',
            'service',
            'biller',
            'resultBy',
            'approver',
            'productOrServiceRequest.payment',
        
            'productOrServiceRequest.payment.user',
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');
        
        if ($request->filled('doctor_id')) $query->where('doctor_id', $request->doctor_id);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));
        if ($request->filled('status')) $query->where('status', $request->status);

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
                'sample_by' => '-',
                'result_by' => $row->resultBy?->firstname ? ($row->resultBy->firstname . ' ' . ($row->resultBy->surname ?? '')) : '-',
                'approved_by' => $row->approver?->firstname ? ($row->approver->firstname . ' ' . ($row->approver->surname ?? '')) : '-',
                'billed_by' => $row->biller?->firstname ? ($row->biller->firstname . ' ' . ($row->biller->surname ?? '')) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'ImagingServiceRequest'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Ordered', 'value' => number_format((clone $kpiQuery)->where('status', 1)->count()), 'color' => '#ffc107'],
                ['label' => 'Image Captured', 'value' => number_format((clone $kpiQuery)->where('status', 2)->count()), 'color' => '#0dcaf0'],
                ['label' => 'Result Entered', 'value' => number_format((clone $kpiQuery)->where('status', 3)->count()), 'color' => '#0d6efd'],
                ['label' => 'Approved', 'value' => number_format((clone $kpiQuery)->where('status', 4)->count()), 'color' => '#198754'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 6: Procedures
     */
    protected function proceduresData(Request $request)
    {
        $query = Procedure::with([
            'patient.user',
            'patient.hmo.scheme',
            'requestedByUser', // effectively doctor
            'service',
            'billedByUser',
            'productOrServiceRequest.payment',
        
            'productOrServiceRequest.payment.user',
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');
        
        if ($request->filled('doctor_id')) $query->where('requested_by', $request->doctor_id);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));
        if ($request->filled('status')) $query->where('procedure_status', $request->status);
        if ($request->filled('outcome')) $query->where('outcome', $request->outcome);

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
                'procedure' => $row->service?->service_name ?? ($row->is_free_form ? $row->free_form_name : '-'),
                'doctor' => $row->requestedByUser?->firstname ? ($row->requestedByUser->firstname . ' ' . ($row->requestedByUser->surname ?? '')) : '-',
                'status' => '<span class="badge bg-'.($statusColors[$row->procedure_status] ?? 'secondary').'">'.ucfirst(str_replace('_', ' ', $row->procedure_status ?? '')).'</span>',
                'consent' => $row->consent_status === 'obtained' ? '<span class="badge bg-success">Obtained</span>' : '<span class="badge bg-danger">Not Obtained</span>',
                'outcome' => $row->outcome ? ucfirst($row->outcome) : '-',
                'or' => $row->operating_room ?? '-',
                'billed_by' => $row->billedByUser?->firstname ? ($row->billedByUser->firstname . ' ' . ($row->billedByUser->surname ?? '')) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'Procedure'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Requested', 'value' => number_format((clone $kpiQuery)->where('procedure_status', 'requested')->count()), 'color' => '#ffc107'],
                ['label' => 'In Progress', 'value' => number_format((clone $kpiQuery)->where('procedure_status', 'in_progress')->count()), 'color' => '#0dcaf0'],
                ['label' => 'Completed', 'value' => number_format((clone $kpiQuery)->where('procedure_status', 'completed')->count()), 'color' => '#198754'],
                ['label' => 'Cancelled', 'value' => number_format((clone $kpiQuery)->where('procedure_status', 'cancelled')->count()), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 7: Referrals Issued
     * (Same as Reception Tab 3, but filtered by referring_doctor_id globally or explicitly)
     */
    protected function referralsData(Request $request)
    {
        $query = SpecialistReferral::with([
            'patient.user',
            'patient.hmo.scheme',
            'referringDoctor',
            'referringClinic',
            'targetClinic',
            'actionedBy',
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        
        if ($request->filled('referring_doctor_id')) $query->where('referring_doctor_id', $request->referring_doctor_id);
        if ($request->filled('hmo_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('id', $request->hmo_id));
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('referral_type')) $query->where('referral_type', $request->referral_type);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;

            $statusColors = ['pending' => 'warning text-dark', 'booked' => 'info', 'referred_out' => 'primary', 'completed' => 'success', 'declined' => 'danger', 'cancelled' => 'secondary'];

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'referring_doctor' => $row->referringDoctor?->firstname ? ($row->referringDoctor->firstname . ' ' . ($row->referringDoctor->surname ?? '')) : '-',
                'type' => '<span class="badge bg-' . ($row->referral_type === 'external' ? 'danger' : 'primary') . '">' . ucfirst($row->referral_type ?? '-') . '</span>',
                'target' => $row->referral_type === 'external' 
                    ? '<small class="text-muted">' . e($row->external_facility_name ?? '-') . '</small>'
                    : ($row->targetClinic?->name ?? '-'),
                'urgency' => '<span class="badge bg-' . ($row->urgency === 'emergency' ? 'danger' : ($row->urgency === 'urgent' ? 'warning text-dark' : 'light text-dark border')) . '">' . ucfirst($row->urgency ?? '-') . '</span>',
                'status' => '<span class="badge bg-' . ($statusColors[$row->status] ?? 'secondary') . ' font-weight-bold">' . ucfirst($row->status ?? '-') . '</span>',
                'actioned_by' => $row->actionedBy?->firstname ? ($row->actionedBy->firstname . ' ' . ($row->actionedBy->surname ?? '')) : '-',
                'payable' => '-', // Missing direct billing relation on referral, would need context
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'SpecialistReferral'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Issued', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Internal', 'value' => number_format((clone $kpiQuery)->where('referral_type', 'internal')->count()), 'color' => '#0d6efd'],
                ['label' => 'External', 'value' => number_format((clone $kpiQuery)->where('referral_type', 'external')->count()), 'color' => '#dc3545'],
                ['label' => 'Pending', 'value' => number_format((clone $kpiQuery)->where('status', 'pending')->count()), 'color' => '#ffc107'],
                ['label' => 'Referred Out', 'value' => number_format((clone $kpiQuery)->where('status', 'referred_out')->count()), 'color' => '#6610f2'],
            ];
        }, $kpiQuery);
    }
}
