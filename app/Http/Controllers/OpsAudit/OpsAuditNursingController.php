<?php

namespace App\Http\Controllers\OpsAudit;

use App\Models\AdmissionRequest;
use App\Models\NursingNote;
use App\Models\ProductOrServiceRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OpsAuditNursingController extends OpsAuditBaseController
{
    public function index(Request $request)
    {
        $wards = \App\Models\Ward::orderBy('name')->pluck('name', 'id');
        $hmos = \App\Models\Hmo::with('scheme')->orderBy('name')->get()->groupBy(fn ($hmo) => $hmo->scheme ? $hmo->scheme->name : 'Other Schemes');
        $hmoSchemes = \App\Models\HmoScheme::orderBy('name')->pluck('name', 'id');
        $stores = $this->getPermittedStoresForFilter(['roles' => ['ward', 'department']]);

        return view('admin.ops_audit.nursing', compact('wards', 'hmos', 'hmoSchemes', 'stores'));
    }

    public function data(Request $request, $tab)
    {
        if ($request->isMethod('post') && in_array($request->action, ['bulk_stamp_preview', 'bulk_stamp'])) {
            $modelMap = [
                'admissions' => AdmissionRequest::class,
                'discharges' => AdmissionRequest::class,
                'notes' => NursingNote::class,
                'bills' => ProductOrServiceRequest::class,
                'requisitions' => \App\Models\StoreRequisition::class,
            ];
            $request->merge(['zone_key' => 'ops_audit.nursing.' . $tab]);

            return $this->handleBulkStamp($request, $tab, $modelMap);
        }

        switch ($tab) {
            case 'admissions':
                return $this->admissionsData($request);
            case 'discharges':
                return $this->dischargesData($request);
            case 'notes':
                return $this->notesData($request);
            case 'bills':
                return $this->billsData($request);
            case 'requisitions':
                return $this->moduleRequisitionsData($request, ['roles' => ['ward', 'department']]);
            default:
                return response()->json(['error' => 'Invalid tab'], 400);
        }
    }

    /**
     * Resolve ward and bed display data with emergency intake awareness.
     */
    protected function resolveWardAndBed($row): array
    {
        $isEmergency = ($row->priority === 'emergency')
            || !empty($row->esi_level)
            || str_contains($row->admission_reason ?? '', '[EMERGENCY INTAKE]');

        $emergencyWard = \App\Models\Ward::where('type', 'emergency')->first();

        $wardName = $row->bed?->wardRelation?->name
            ?? ($row->bed?->ward ?? null)
            ?? $row->preferredWard?->name;

        if (!$wardName && $isEmergency) {
            $wardName = $emergencyWard?->name ?? 'Emergency Ward';
        }

        $wardHtml = $wardName ? htmlspecialchars($wardName) : '<span class="text-muted">-</span>';
        if ($isEmergency && (!$row->bed || ($row->bed && $row->bed->wardRelation?->type === 'emergency'))) {
            $wardHtml = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="mdi mdi-ambulance me-1"></i>' . htmlspecialchars($wardName ?? 'Emergency Ward') . '</span>';
        }

        if ($row->bed?->name) {
            $bedHtml = '<span class="fw-semibold">' . htmlspecialchars($row->bed->name) . '</span>';
        } elseif ($isEmergency) {
            $bedHtml = '<span class="badge bg-warning-subtle text-dark border border-warning-subtle"><i class="mdi mdi-alert-circle-outline me-1"></i>No Bed (Emergency)</span>';
        } else {
            $bedHtml = '<span class="text-muted fst-italic">Pending Bed</span>';
        }

        return [
            'is_emergency' => $isEmergency,
            'ward_html' => $wardHtml,
            'bed_html' => $bedHtml,
        ];
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
            'preferredWard',
            'bed.wardRelation',
            'productOrServiceRequest.payment.user',
            'bills.payment.staff_user',
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);
        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');
        $this->applyItemFilters($query, $request, 'productOrServiceRequest');

        if ($request->filled('ward_id')) {
            $wardId = $request->ward_id;
            $selectedWard = \App\Models\Ward::find($wardId);
            $isEmergencyWard = $selectedWard && $selectedWard->type === 'emergency';

            $query->where(function ($q) use ($wardId, $isEmergencyWard) {
                $q->whereHas('bed', fn ($bq) => $bq->where('ward_id', $wardId))
                  ->orWhere('preferred_ward_id', $wardId);

                if ($isEmergencyWard) {
                    $q->orWhere('priority', 'emergency')
                      ->orWhereNotNull('esi_level')
                      ->orWhere('admission_reason', 'like', '%[EMERGENCY INTAKE]%');
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('admission_status', $request->status);
        } else {
            // Default active admissions tab to non-discharged
            $query->where('discharged', 0);
        }

        if ($request->filled('hmo_id')) {
            $query->whereHas('patient.hmo', fn ($q) => $q->where('id', $request->hmo_id));
        }
        if ($request->filled('hmo_scheme_id')) {
            $query->whereHas('patient.hmo', fn ($q) => $q->where('hmo_scheme_id', $request->hmo_scheme_id));
        }
        if ($request->filled('gender')) {
            $query->whereHas('patient.user', fn ($q) => $q->where('gender', $request->gender));
        }

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn ($q) => $q->orderBy('created_at', 'desc'), function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;

            $wardAndBed = $this->resolveWardAndBed($row);
            $isEmergency = $wardAndBed['is_emergency'];

            $statusColors = [
                'pending_checklist' => 'warning text-dark',
                'checklist_complete' => 'info text-dark',
                'admitted' => 'primary',
                'discharge_requested' => 'warning text-dark',
                'discharge_checklist' => 'info text-dark',
                'discharged' => 'success',
            ];
            $statusBadge = '<span class="badge bg-' . ($statusColors[$row->admission_status] ?? 'secondary') . '">' . ucfirst(str_replace('_', ' ', $row->admission_status ?? ($row->discharged ? 'discharged' : 'admitted'))) . '</span>';

            $admitDate = $row->bed_assign_date ?? $row->created_at;
            $dischargeDate = $row->discharge_date;
            $los = $admitDate ? (Carbon::parse($admitDate)->diffInDays($dischargeDate ? Carbon::parse($dischargeDate) : now()) + 1) . ' days' : '-';

            $patientHtml = $this->renderPatient($user, $patient, $hmo);
            if ($isEmergency) {
                $badge = '<div class="mb-1"><span class="badge bg-danger text-white"><i class="mdi mdi-ambulance me-1"></i>EMERGENCY</span>';
                if ($row->esi_level) {
                    $badge .= ' <span class="badge bg-dark text-white">ESI ' . $row->esi_level . '</span>';
                }
                $badge .= '</div>';
                $patientHtml = $badge . $patientHtml;
            }

            // Aggregate bills for this admission request
            $bills = $row->bills;
            $totalAmount = $bills->sum('amount');

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $patientHtml,
                'hmo' => $this->renderHmo($hmo),
                'ward' => $wardAndBed['ward_html'],
                'bed' => $wardAndBed['bed_html'],
                'status' => $statusBadge,
                'los' => $los,
                'total_bill' => $totalAmount > 0 ? '₦' . number_format($totalAmount, 2) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'AdmissionRequest'),
            ];
        }, function ($kpiQuery) {
            $totalCount = (clone $kpiQuery)->count();
            $admittedCount = (clone $kpiQuery)->where('admission_status', 'admitted')->count();
            $emergencyCount = (clone $kpiQuery)->where(function ($q) {
                $q->where('priority', 'emergency')
                  ->orWhereNotNull('esi_level')
                  ->orWhere('admission_reason', 'like', '%[EMERGENCY INTAKE]%');
            })->count();

            return [
                ['label' => 'Total Active', 'value' => number_format($totalCount), 'color' => '#0d6efd'],
                ['label' => 'Bed Admitted', 'value' => number_format($admittedCount), 'color' => '#198754'],
                ['label' => 'Emergency Intakes', 'value' => number_format($emergencyCount), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    /**
     * Tab 2: Recent Discharges
     */
    protected function dischargesData(Request $request)
    {
        $query = AdmissionRequest::with([
            'patient.user',
            'patient.hmo.scheme',
            'doctor',
            'discharger',
            'preferredWard',
            'bed.wardRelation',
            'productOrServiceRequest.payment.user',
            'bills.payment.staff_user',
        ])->where('discharged', 1);

        // Date filter applied to discharge_date (with fallback to updated_at)
        if ($request->filled('start_date')) {
            $query->where(function ($q) use ($request) {
                $q->whereDate('discharge_date', '>=', $request->start_date)
                  ->orWhere(fn ($sub) => $sub->whereNull('discharge_date')->whereDate('updated_at', '>=', $request->start_date));
            });
        }
        if ($request->filled('end_date')) {
            $query->where(function ($q) use ($request) {
                $q->whereDate('discharge_date', '<=', $request->end_date)
                  ->orWhere(fn ($sub) => $sub->whereNull('discharge_date')->whereDate('updated_at', '<=', $request->end_date));
            });
        }

        $this->applyPaymentFilters($query, $request, 'productOrServiceRequest');
        $this->applyItemFilters($query, $request, 'productOrServiceRequest');

        if ($request->filled('ward_id')) {
            $wardId = $request->ward_id;
            $selectedWard = \App\Models\Ward::find($wardId);
            $isEmergencyWard = $selectedWard && $selectedWard->type === 'emergency';

            $query->where(function ($q) use ($wardId, $isEmergencyWard) {
                $q->whereHas('bed', fn ($bq) => $bq->where('ward_id', $wardId))
                  ->orWhere('preferred_ward_id', $wardId);

                if ($isEmergencyWard) {
                    $q->orWhere('priority', 'emergency')
                      ->orWhereNotNull('esi_level')
                      ->orWhere('admission_reason', 'like', '%[EMERGENCY INTAKE]%');
                }
            });
        }

        if ($request->filled('hmo_id')) {
            $query->whereHas('patient.hmo', fn ($q) => $q->where('id', $request->hmo_id));
        }
        if ($request->filled('hmo_scheme_id')) {
            $query->whereHas('patient.hmo', fn ($q) => $q->where('hmo_scheme_id', $request->hmo_scheme_id));
        }
        if ($request->filled('gender')) {
            $query->whereHas('patient.user', fn ($q) => $q->where('gender', $request->gender));
        }

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn ($q) => $q->orderBy('discharge_date', 'desc'), function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;

            $wardAndBed = $this->resolveWardAndBed($row);
            $isEmergency = $wardAndBed['is_emergency'];

            $admitDate = $row->bed_assign_date ?? $row->created_at;
            $dischargeDate = $row->discharge_date ?? $row->updated_at;
            $losDays = $admitDate ? (Carbon::parse($admitDate)->diffInDays(Carbon::parse($dischargeDate)) + 1) : 1;

            $patientHtml = $this->renderPatient($user, $patient, $hmo);
            if ($isEmergency) {
                $badge = '<div class="mb-1"><span class="badge bg-danger text-white"><i class="mdi mdi-ambulance me-1"></i>EMERGENCY</span>';
                if ($row->esi_level) {
                    $badge .= ' <span class="badge bg-dark text-white">ESI ' . $row->esi_level . '</span>';
                }
                $badge .= '</div>';
                $patientHtml = $badge . $patientHtml;
            }

            $bills = $row->bills;
            $totalAmount = $bills->sum('amount');

            $dischargerName = $row->discharger
                ? trim($row->discharger->firstname . ' ' . ($row->discharger->surname ?? ''))
                : '-';

            $reasonText = $row->discharge_reason ?? $row->discharge_note ?? 'Standard Discharge';

            return [
                'discharge_date' => $row->discharge_date ? Carbon::parse($row->discharge_date)->format('d M Y H:i') : ($row->updated_at ? Carbon::parse($row->updated_at)->format('d M Y H:i') : '-'),
                'admit_date' => $admitDate ? Carbon::parse($admitDate)->format('d M Y') : '-',
                'patient' => $patientHtml,
                'hmo' => $this->renderHmo($hmo),
                'ward' => $wardAndBed['ward_html'],
                'bed' => $wardAndBed['bed_html'],
                'los' => $losDays . ' day' . ($losDays > 1 ? 's' : ''),
                'reason' => '<span class="text-truncate d-inline-block" style="max-width: 180px;" title="' . htmlspecialchars($reasonText, ENT_QUOTES) . '">' . htmlspecialchars($reasonText) . '</span>',
                'discharged_by' => htmlspecialchars($dischargerName),
                'total_bill' => $totalAmount > 0 ? '₦' . number_format($totalAmount, 2) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'AdmissionRequest'),
            ];
        }, function ($kpiQuery) {
            $totalCount = (clone $kpiQuery)->count();
            $emergencyCount = (clone $kpiQuery)->where(function ($q) {
                $q->where('priority', 'emergency')
                  ->orWhereNotNull('esi_level')
                  ->orWhere('admission_reason', 'like', '%[EMERGENCY INTAKE]%');
            })->count();

            // Calculate average LOS safely in PHP
            $dischargedRows = (clone $kpiQuery)->select('id', 'bed_assign_date', 'created_at', 'discharge_date', 'updated_at')->take(200)->get();
            $totalLos = 0;
            foreach ($dischargedRows as $dr) {
                $a = $dr->bed_assign_date ?? $dr->created_at;
                $d = $dr->discharge_date ?? $dr->updated_at;
                if ($a && $d) {
                    $totalLos += (Carbon::parse($a)->diffInDays(Carbon::parse($d)) + 1);
                }
            }
            $avgLos = $dischargedRows->count() > 0 ? round($totalLos / $dischargedRows->count(), 1) : 0;

            return [
                ['label' => 'Total Discharged', 'value' => number_format($totalCount), 'color' => '#198754'],
                ['label' => 'Avg Length of Stay', 'value' => $avgLos . ' days', 'color' => '#0d6efd'],
                ['label' => 'Emergency Discharges', 'value' => number_format($emergencyCount), 'color' => '#dc3545'],
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

        if ($request->filled('completed')) {
            $query->where('completed', $request->completed);
        }

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn ($q) => $q, function ($row) {
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
            return [
                ['label' => 'Total Notes', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Completed', 'value' => number_format((clone $kpiQuery)->where('completed', 1)->count()), 'color' => '#198754'],
                ['label' => 'Pending', 'value' => number_format((clone $kpiQuery)->where('completed', 0)->count()), 'color' => '#ffc107'],
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
            'product.category',
            'service',
])->where(function ($q) {
    $q->whereNotNull('admission_request_id')
      ->orWhereHas('staff', function ($q2) {
          $q2->whereHas('roles', fn ($r) => $r->where('name', 'NURSE'));
      });
});

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);

        if ($request->filled('ward_id')) {
            $wardId = $request->ward_id;
            $query->whereHas('admissionRequest', function ($aq) use ($wardId) {
                $aq->where(function ($sub) use ($wardId) {
                    $sub->whereHas('bed', fn ($bq) => $bq->where('ward_id', $wardId))
                        ->orWhere('preferred_ward_id', $wardId);
                });
            });
        }

        if ($request->filled('hmo_id')) {
            $query->whereHas('patient.hmo', fn ($q) => $q->where('id', $request->hmo_id));
        }

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn ($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $payment = $row->payment;

            $itemName = '-';
            if ($row->type === 'product' && $row->product) {
                $itemName = $row->product->product_name;
            }
            if ($row->type === 'service' && $row->service) {
                $itemName = $row->service->service_name;
            }

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
            return [
                ['label' => 'Total Bills', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Total Amount', 'value' => '₦' . number_format((clone $kpiQuery)->sum('amount'), 2), 'color' => '#6610f2'],
                ['label' => 'Payable', 'value' => '₦' . number_format((clone $kpiQuery)->sum('payable_amount'), 2), 'color' => '#198754'],
                ['label' => 'Claims', 'value' => '₦' . number_format((clone $kpiQuery)->sum('claims_amount'), 2), 'color' => '#0dcaf0'],
            ];
        }, $kpiQuery);
    }
}
