<?php

namespace App\Http\Controllers\OpsAudit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\AuditMark;

abstract class OpsAuditBaseController extends Controller
{
    /**
     * Shared helper to apply date filtering.
     */
    protected function applyDateFilter($query, Request $request, $column = 'created_at')
    {
        $start = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $end = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $query->whereBetween($column, [$start, $end]);
    }

    /**
     * Shared helper to apply shift filtering.
     */
    protected function applyShiftFilter($query, Request $request, $column = 'created_at')
    {
        if ($request->filled('shift_start') && $request->filled('shift_end')) {
            $query->whereRaw("TIME($column) BETWEEN ? AND ?", [$request->shift_start, $request->shift_end]);
        }
    }

    /**
     * Shared helper to render patient details.
     */
    protected function renderPatient($user, $patient, $hmo)
    {
        if (!$patient) return '-';

        $patientId = $patient->file_no ?? $patient->old_patient_id ?? 'Unknown';
        $name = $user ? trim($user->firstname . ' ' . ($user->othername ?? '') . ' ' . $user->surname) : 'Unknown Patient';
        
        $hmoHtml = '';
        if ($hmo) {
            $scheme = $hmo->scheme ? $hmo->scheme->name : '';
            $hmoHtml = '<small class="text-info d-block" style="font-size:0.65rem; line-height: 1;"><i class="mdi mdi-shield-account"></i> ' . e($hmo->name) . ($scheme ? '<br><span class="text-muted">' . e($scheme) . '</span>' : '') . '</small>';
        } else {
            $hmoHtml = '<small class="text-success d-block" style="font-size:0.65rem; line-height: 1;"><i class="mdi mdi-cash"></i> Private <span class="text-muted text-xs">(Self/Private)</span></small>';
        }

        return '
            <div style="line-height: 1.1;">
                <div class="font-weight-bold text-dark mb-1" style="font-size: 0.8rem;"><i class="mdi mdi-account text-primary"></i> ' . e($name) . '</div>
                <small class="text-muted d-block mb-1">#' . e($patientId) . '</small>
                ' . $hmoHtml . '
            </div>
        ';
    }

    /**
     * Standardized helper to render payment entity details (Patient, Organization, or Staff)
     */
    public function renderPaymentEntityDetails($r, $defaultName = 'Walk-in / N/A')
    {
        if (!$r) {
            return '<div class="font-weight-bold text-dark">' . $defaultName . '</div>';
        }

        $isStaff = ($r->payment_type === 'STAFF_BILL_SETTLEMENT' || $r->payment_method === 'BILL_TO_STAFF' || $r->payment_method === 'STAFF_BILL');
        $isOrg = ($r->payment_type === 'ORGANIZATION_BILL_SETTLEMENT' || $r->payment_method === 'BILL_TO_ORG' || $r->payment_method === 'ORG_BILL');

        if ($isStaff) {
            $staffBill = \App\Models\StaffBill::where('settlement_payment_id', $r->id)->orWhere('payment_id', $r->id)->with('staffUser')->first();
            if (!$staffBill) {
                $alloc = \Illuminate\Support\Facades\DB::table('staff_bill_payment_allocations')->where('payment_id', $r->id)->first();
                if ($alloc) {
                    $staffBill = \App\Models\StaffBill::with('staffUser')->find($alloc->staff_bill_id);
                }
            }
            $name = $staffBill->staffUser->firstname ?? 'Staff Member';
            if (isset($staffBill->staffUser->surname)) {
                $name .= ' ' . $staffBill->staffUser->surname;
            }
            return '<div class="font-weight-bold text-dark"><i class="mdi mdi-account-tie text-primary"></i> ' . e($name) . '</div><small class="badge bg-primary text-white mt-1">Staff Bill</small>';
        }

        if ($isOrg) {
            $orgBill = \App\Models\OrganizationBill::where('settlement_payment_id', $r->id)->orWhere('payment_id', $r->id)->with('organization')->first();
            if (!$orgBill) {
                $alloc = \Illuminate\Support\Facades\DB::table('organization_bill_payment_allocations')->where('payment_id', $r->id)->first();
                if ($alloc) {
                    $orgBill = \App\Models\OrganizationBill::with('organization')->find($alloc->organization_bill_id);
                }
            }
            if (!$orgBill) {
                $posr = \App\Models\ProductOrServiceRequest::where('payment_id', $r->id)->with('organization')->first();
                if ($posr && $posr->organization) {
                    $name = $posr->organization->name ?? $posr->organization->company_name;
                    return '<div class="font-weight-bold text-dark"><i class="mdi mdi-domain text-info"></i> ' . e($name) . '</div><small class="badge bg-info text-white mt-1">Corporate Retainership</small>';
                }
            }
            $name = $orgBill->organization->name ?? $orgBill->organization->company_name ?? 'Organization';
            return '<div class="font-weight-bold text-dark"><i class="mdi mdi-domain text-info"></i> ' . e($name) . '</div><small class="badge bg-info text-white mt-1">Corporate Retainership</small>';
        }

        $patient = $r->patient;
        if (!$patient) {
            $posr = \App\Models\ProductOrServiceRequest::where('payment_id', $r->id)->with('patient.user', 'patient.hmo.scheme')->first();
            if ($posr && $posr->patient) {
                $patient = $posr->patient;
            }
        }

        if ($patient) {
            return $this->renderPatient($patient->user, $patient, $patient->hmo);
        }

        return '<div class="font-weight-bold text-dark">' . $defaultName . '</div>';
    }

    /**
     * Shared helper to render Bank details for Cashbook/Payments tables
     */
    public function renderBankDetails($payment)
    {
        if (in_array($payment->payment_method, ['POS', 'TRANSFER', 'BANK_TRANSFER', 'CHEQUE', 'MOBILE'])) {
            return '<div class="font-weight-bold text-primary" style="font-size:0.8rem;"><i class="mdi mdi-bank"></i> ' . e($payment->bank?->name ?? 'No Bank') . '</div>';
        }
        return '-';
    }

    /**
     * Shared helper to render HMO details.
     */
    protected function renderHmo($hmo)
    {
        if (!$hmo) return '<span class="text-muted" style="font-size:0.75rem;">Cash</span>';
        return '<small class="font-weight-bold text-info">' . e($hmo->name ?? '-') . '</small>' .
            ($hmo->scheme ? '<br><small class="text-muted" style="font-size:0.7rem;">' . e($hmo->scheme->name) . '</small>' : '');
    }

    /**
     * Shared helper to render audit stamp/flag/query actions.
     */
    protected function renderAuditAction($record, $modelType)
    {
        if (!$record) return '';

        $fullModelClass = str_starts_with($modelType, 'App\\Models\\') ? $modelType : 'App\\Models\\' . $modelType;
        $shortModelClass = class_basename($fullModelClass);

        // Active query?
        $activeQuery = null;
        if (isset($record->is_queried) && $record->is_queried && empty($record->query_resolved_at)) {
            $activeQuery = (object)[
                'auditor' => (object)['name' => 'Auditor'], 
                'query_notes' => $record->query_notes ?? 'Flagged',
                'created_at' => isset($record->queried_at) ? \Carbon\Carbon::parse($record->queried_at) : now()
            ];
        } else {
            $activeQuery = AuditMark::with('auditor')
                ->where(fn($q) => $q->where('auditable_type', $fullModelClass)->orWhere('auditable_type', $shortModelClass))
                ->where('auditable_id', $record->id)
                ->where('status', 'queried')
                ->latest()->first();
        }

        // Latest audit
        $latestAudit = null;
        if (isset($record->is_audited) && $record->is_audited) {
            $latestAudit = (object)['auditor' => (object)['name' => 'Auditor'], 'created_at' => isset($record->audited_at) ? Carbon::parse($record->audited_at) : now()];
        } else {
            $latestAudit = AuditMark::with('auditor')
                ->where(fn($q) => $q->where('auditable_type', $fullModelClass)->orWhere('auditable_type', $shortModelClass))
                ->where('auditable_id', $record->id)
                ->where('status', 'audited')
                ->latest()->first();
        }

        $html = '<div class="d-flex gap-1 flex-wrap align-items-center">';

        $typeMap = [
            'App\\Models\\AdmissionRequest' => 'admission',
            'App\\Models\\StoreRequisition' => 'requisition',
            'App\\Models\\StoreRequisitionItem' => 'requisition',
        ];
        
        $detailsType = $typeMap[$fullModelClass] ?? null;
        if ($detailsType) {
            $detailsId = $record->id;
            if ($fullModelClass === 'App\\Models\\StoreRequisitionItem') {
                $detailsId = $record->store_requisition_id;
            }
            $html .= '<button class="btn btn-sm btn-info text-white px-2 py-1 text-nowrap" onclick="openOpsAuditDetail(\'' . $detailsType . '\', ' . $detailsId . ')" title="View Details"><i class="mdi mdi-information-outline me-1"></i> Details</button>';
        }

        if ($activeQuery) {
            $auditorName = $activeQuery->auditor->name ?? 'Auditor';
            $notes = htmlspecialchars($activeQuery->query_notes ?? 'Flagged', ENT_QUOTES);
            $html .= '<button class="btn btn-sm btn-warning text-dark font-weight-bold shadow-sm px-2 py-1 text-nowrap" onclick="openResolveQueryModal(\'' . addslashes($fullModelClass) . '\', ' . $record->id . ')" title="Queried: ' . $notes . '"><i class="mdi mdi-alert-circle me-1"></i> Resolve</button>';
        } else {
            if ($latestAudit) {
                $html .= '<button class="btn btn-sm btn-success px-2 py-1 text-nowrap" onclick="viewTimeline(\'' . addslashes($fullModelClass) . '\', ' . $record->id . ')" title="View Audit Timeline"><i class="mdi mdi-check-decagram me-1"></i> Audited</button>';
            } else {
                $html .= '<button class="btn btn-sm btn-outline-success audit-tick-btn px-2 py-1 text-nowrap" onclick="markAudited(this, \'' . addslashes($fullModelClass) . '\', ' . $record->id . ')"><i class="mdi mdi-check me-1"></i> Stamp</button>';
            }
            $html .= '<button class="btn btn-sm btn-outline-warning px-2 py-1 text-nowrap" onclick="openRaiseQueryModal(\'' . addslashes($fullModelClass) . '\', ' . $record->id . ')"><i class="mdi mdi-flag me-1"></i> Flag</button>';
        }
        $html .= '</div>';

        if ($activeQuery) {
            $notes = htmlspecialchars($activeQuery->query_notes ?? 'Flagged', ENT_QUOTES);
            $queryTime = (isset($activeQuery->created_at) && is_object($activeQuery->created_at)) ? $activeQuery->created_at->format('d M y H:i') : 'Recently';
            $queriedBy = 'Auditor';
            if (isset($activeQuery->auditor)) {
                $queriedBy = trim(($activeQuery->auditor->firstname ?? $activeQuery->auditor->name ?? 'Auditor') . ' ' . ($activeQuery->auditor->surname ?? ''));
            }

            $html .= '<div class="mt-1" style="cursor: pointer; padding: 4px; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background=\'#fff3cd\'" onmouseout="this.style.background=\'transparent\'" onclick="viewTimeline(\'' . addslashes($fullModelClass) . '\', ' . $record->id . ')" title="Click to view full timeline">';
            $html .= '<small class="d-block text-danger font-weight-bold" style="font-size:0.72rem;"><i class="mdi mdi-alert-circle me-1"></i>Queried by ' . htmlspecialchars($queriedBy, ENT_QUOTES) . '</small>';
            $html .= '<small class="d-block text-muted" style="font-size: 0.7rem;">' . $queryTime . '</small>';
            $html .= '<div class="text-muted mt-1" style="font-size: 0.7rem; white-space: normal; line-height: 1.2; word-break: break-word; max-width: 200px;">' . $notes . '</div>';
            $html .= '</div>';
        } elseif ($latestAudit) {
            $stampedTime = (isset($latestAudit->created_at) && is_object($latestAudit->created_at)) ? $latestAudit->created_at->format('d M y H:i') : 'Recently';
            $auditorName = '-';
            if (isset($latestAudit->auditor)) {
                $auditorName = trim(($latestAudit->auditor->firstname ?? $latestAudit->auditor->name ?? 'Auditor') . ' ' . ($latestAudit->auditor->surname ?? ''));
            }
            $html .= '<div class="mt-1" style="cursor: pointer; padding: 4px; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background=\'#d1e7dd\'" onmouseout="this.style.background=\'transparent\'" onclick="viewTimeline(\'' . addslashes($fullModelClass) . '\', ' . $record->id . ')" title="Click to view full timeline">';
            $html .= '<small class="d-block text-success font-weight-bold" style="font-size:0.72rem;"><i class="mdi mdi-check-all me-1"></i>Stamped by ' . htmlspecialchars($auditorName, ENT_QUOTES) . '</small>';
            $html .= '<small class="d-block text-muted" style="font-size: 0.7rem;">' . $stampedTime . '</small>';
            $html .= '</div>';
        }

        return $html;
    }

    protected function getPermittedStoresForFilter(?array $config)
    {
        $query = \App\Models\Store::orderBy('store_name');

        if ($config && (!empty($config['roles']) || !empty($config['name_match']))) {
            $query->where(function ($q) use ($config) {
                $q->where('distribution_role', 'main_store');

                if (!empty($config['roles'])) {
                    $q->orWhereIn('distribution_role', $config['roles']);
                }
                if (!empty($config['name_match'])) {
                    foreach ($config['name_match'] as $match) {
                        $q->orWhere('store_name', 'like', $match);
                    }
                }
            });
        }

        return $query->get()->mapWithKeys(fn($s) => [$s->id => trim($s->store_name . ' (' . $s->distributionRoleLabel() . ')')]);
    }

    protected function buildDataTableResponse($query, Request $request, callable $customizer, callable $rowMapper, callable $kpiBuilder, $kpiQuery = null)
    {
        if ($request->input('action') === 'print') {
            $query = $customizer($query);
            $records = $query->get()->map($rowMapper)->values();

            $kpis = [];
            if ($kpiQuery) {
                try {
                    $kpis = $kpiBuilder($kpiQuery);
                } catch (\Exception $e) {
                }
            }

            $viewData = [
                'data' => $records,
                'kpis' => $kpis,
                'appsettings' => appsettings(),
                'printer' => userfullname(\Illuminate\Support\Facades\Auth::id()),
                'print_date' => \Carbon\Carbon::now()->format('d M Y H:i'),
                'filters' => [
                    'date_from' => $request->filled('start_date') ? Carbon::parse($request->start_date)->format('d M Y') : now()->subDays(30)->format('d M Y'),
                    'date_to' => $request->filled('end_date') ? Carbon::parse($request->end_date)->format('d M Y') : now()->format('d M Y'),
                ],
                'tab_name' => ucwords(str_replace('_', ' ', $request->input('tab', 'Report')))
            ];

            return view('admin.ops_audit.print', $viewData);
        }

        $query = $customizer($query);

        $recordsTotal = (clone $query)->count();

        // Basic patient search capability
        $search = $request->input('search.value');
        if ($search) {
            $query->where(function ($q) use ($search) {
                // If model has a patient relationship
                if (method_exists($q->getModel(), 'patient')) {
                    $q->whereHas('patient.user', function ($q2) use ($search) {
                        $q2->where('firstname', 'like', "%$search%")
                            ->orWhere('surname', 'like', "%$search%");
                    })->orWhereHas('patient', fn($q2) => $q2->where('file_no', 'like', "%$search%"));
                }
            });
        }

        $recordsFiltered = (clone $query)->count();

        $orderCol = $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc');
        // Sort by created_at by default
        $query->orderBy('created_at', $orderDir);

        $start = $request->input('start', 0);
        $length = $request->input('length', 25);
        $data = $query->skip($start)->take($length)->get();

        $rows = $data->map($rowMapper)->values();

        $kpis = [];
        if ($kpiQuery) {
            try {
                $kpis = $kpiBuilder($kpiQuery);
            } catch (\Exception $e) {
                // Ignore KPI generation errors
            }
        }

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
            'kpis' => $kpis,
        ]);
    }

    /**
     * Shared bulk stamp handler.
     */
    protected function processBulkStamp(Request $request, $tab, $modelMap)
    {
        $modelClass = $modelMap[$tab] ?? null;
        if (!$modelClass) {
            return response()->json(['success' => false, 'message' => 'Invalid tab']);
        }

        $query = $modelClass::query();
        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);

        if ($request->action === 'bulk_stamp_preview') {
            $all = $query->get();
            $queried = $all->filter(fn($r) => (isset($r->is_queried) && $r->is_queried && empty($r->query_resolved_at)) || AuditMark::where('auditable_type', $modelClass)->where('auditable_id', $r->id)->where('status', 'queried')->exists())->count();
            $alreadyAudited = $all->filter(fn($r) => (isset($r->is_audited) && $r->is_audited) || AuditMark::where('auditable_type', $modelClass)->where('auditable_id', $r->id)->where('status', 'audited')->exists())->count();
            $valid = $all->count() - $queried - $alreadyAudited;

            $totalAmount = 0;
            $totalPayable = 0;
            $totalClaims = 0;

            foreach ($all as $record) {
                // If the record has productOrServiceRequest or serviceRequest, try to aggregate billing info
                if (method_exists($record, 'productOrServiceRequest') && $record->productOrServiceRequest) {
                    $totalAmount += $record->productOrServiceRequest->amount ?? 0;
                    $totalPayable += $record->productOrServiceRequest->payable_amount ?? 0;
                    $totalClaims += $record->productOrServiceRequest->claims_amount ?? 0;
                } elseif (method_exists($record, 'serviceRequest') && $record->serviceRequest) {
                    $totalAmount += $record->serviceRequest->amount ?? 0;
                    $totalPayable += $record->serviceRequest->payable_amount ?? 0;
                    $totalClaims += $record->serviceRequest->claims_amount ?? 0;
                }
            }

            return response()->json([
                'success' => true,
                'valid' => max(0, $valid),
                'queried' => $queried,
                'already_audited' => $alreadyAudited,
                'monetary' => [
                    'total_amount' => $totalAmount,
                    'total_payable' => $totalPayable,
                    'total_claims' => $totalClaims,
                    'unique_patients' => method_exists($modelClass, 'patient') ? $all->pluck('patient_id')->unique()->count() : 0,
                ],
            ]);
        }

        if ($request->action === 'bulk_stamp') {
            $stamped = 0;
            $skipped = 0;
            $records = $query->get();
            $zoneKey = $request->input('zone_key', 'ops_audit.general.' . $tab);

            foreach ($records as $record) {
                $isQueried = (isset($record->is_queried) && $record->is_queried && empty($record->query_resolved_at)) ||
                    AuditMark::where('auditable_type', $modelClass)->where('auditable_id', $record->id)->where('status', 'queried')->exists();
                $isAudited = (isset($record->is_audited) && $record->is_audited) ||
                    AuditMark::where('auditable_type', $modelClass)->where('auditable_id', $record->id)->where('status', 'audited')->exists();

                if ($isQueried || $isAudited) {
                    $skipped++;
                    continue;
                }

                AuditMark::create([
                    'auditable_type' => $modelClass,
                    'auditable_id' => $record->id,
                    'status' => 'audited',
                    'auditor_id' => auth()->id(),
                    'zone_key' => $zoneKey,
                ]);

                if (isset($record->is_audited)) {
                    $record->update(['is_audited' => true, 'audited_by' => auth()->id(), 'audited_at' => now()]);
                }
                $stamped++;
            }

            return response()->json([
                'success' => true,
                'message' => "$stamped records stamped successfully.",
                'skipped_count' => $skipped,
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action']);
    }

    /**
     * Shared helper to render POSR Payment Information
     */
    protected function renderPaymentInfo($record)
    {
        $posr = null;
        if ($record instanceof \App\Models\ProductOrServiceRequest) {
            $posr = $record;
        } elseif (method_exists($record, 'request_entry') && $record->request_entry instanceof \App\Models\ProductOrServiceRequest) {
            $posr = $record->request_entry;
        } elseif (method_exists($record, 'productOrServiceRequest') && $record->productOrServiceRequest instanceof \App\Models\ProductOrServiceRequest) {
            $posr = $record->productOrServiceRequest;
        } elseif (method_exists($record, 'serviceRequest') && $record->serviceRequest instanceof \App\Models\ProductOrServiceRequest) {
            $posr = $record->serviceRequest;
        } elseif (method_exists($record, 'encounter') && $record->encounter && $record->encounter->productOrServiceRequest instanceof \App\Models\ProductOrServiceRequest) {
            $posr = $record->encounter->productOrServiceRequest;
        } elseif (method_exists($record, 'enrollment') && $record->enrollment && method_exists($record->enrollment, 'serviceRequest') && $record->enrollment->serviceRequest instanceof \App\Models\ProductOrServiceRequest) {
            $posr = $record->enrollment->serviceRequest;
        }

        if (!$posr) {
            // For Payment cashbook rows
            if ($record instanceof \App\Models\Payment) {
                $payment = $record;
                $paymentMethod = $this->formatPaymentMethodWithChannel($payment);
                $cashier = ($payment->user) ? ($payment->user->firstname . ' ' . ($payment->user->surname ?? '')) : '-';
                $time = $payment->created_at ? $payment->created_at->format('d M y H:i') : '-';
                $accountHtml = '';
                if ($payment->payment_method === 'ACCOUNT' && $payment->patient) {
                    $billingPatientId = $payment->patient->billing_patient_id;
                    $account = \App\Models\PatientAccount::where('patient_id', $billingPatientId)->first();
                    if ($account) {
                        $balance = number_format($account->balance, 2);
                        $accountHtml = "<div class='mb-1 text-primary'><strong>Acct Balance:</strong> ₦{$balance}</div>";
                    }
                }

                return "
                    <div style='font-size: 0.75rem; line-height: 1.2;'>
                        <div class='mb-1'><strong>Method:</strong> {$paymentMethod}</div>
                        {$accountHtml}
                        <div class='mb-1'><strong>Cashier:</strong> {$cashier}</div>
                        <div class='text-muted'>{$time}</div>
                    </div>
                ";
            }
            return '<span class="text-muted">-</span>';
        }

        $payment = $posr->payment;
        $payable = number_format((float) $posr->payable_amount, 2);
        $claims = number_format((float) $posr->claims_amount, 2);
        
        if (!$payment) {
            $paymentMethod = '<span class="text-danger fw-bold"><i class="mdi mdi-alert-circle-outline me-1"></i>No Payment</span>';
            $cashier = '-';
            $time = '-';
        } else {
            $paymentMethod = $this->formatPaymentMethodWithChannel($payment);
            $cashier = ($payment->user) ? ($payment->user->firstname . ' ' . ($payment->user->surname ?? '')) : '-';
            $time = $payment->created_at ? $payment->created_at->format('d M y H:i') : '-';
        }

        $hmoHtml = '';
        if ($posr->hmo_id) {
            $hmo = \App\Models\Hmo::with('scheme')->find($posr->hmo_id);
            $hmoName = $hmo ? $hmo->name : 'Unknown HMO';
            $schemeName = ($hmo && $hmo->scheme) ? $hmo->scheme->name : '';
            
            $validationBadge = '<span class="text-secondary fw-bold"><i class="mdi mdi-clock-outline me-1"></i>Claims Pending</span>';
            if ($posr->validation_status === 'approved') {
                $validationBadge = '<span class="text-success fw-bold"><i class="mdi mdi-check-circle me-1"></i>Claims Approved</span>';
            } elseif ($posr->validation_status === 'rejected') {
                $validationBadge = '<span class="text-danger fw-bold"><i class="mdi mdi-close-circle me-1"></i>Claims Rejected</span>';
            }

            $approverName = '';
            if ($posr->validatedBy) {
                $approverName = ' <small class="text-muted d-block mt-1">Processed by: ' . trim($posr->validatedBy->firstname . ' ' . $posr->validatedBy->surname);
                if ($posr->validated_at) {
                    $approverName .= ' on ' . \Carbon\Carbon::parse($posr->validated_at)->format('d M y H:i');
                }
                $approverName .= '</small>';
            }

            $coverageMode = $posr->coverage_mode ? "<span class='text-info fw-bold' style='font-size:0.65rem;'><i class='mdi mdi-shield-check me-1'></i>Mode: {$posr->coverage_mode}</span>" : '<span class="text-muted">Coverage: Not Set</span>';

            $hmoHtml = "
                <div class='mt-1 pt-1 border-top border-light'>
                    <div class='mb-1'><strong>HMO:</strong> {$hmoName} " . ($schemeName ? "<small class='text-muted'>({$schemeName})</small>" : '') . "</div>
                    <div class='mb-1'>{$coverageMode}</div>
                    <div>{$validationBadge}{$approverName}</div>
                </div>
            ";
        }

        $accountHtml = '';
        if ($payment && $payment->payment_method === 'ACCOUNT' && $posr->patient) {
            $billingPatientId = $posr->patient->billing_patient_id;
            $account = \App\Models\PatientAccount::where('patient_id', $billingPatientId)->first();
            if ($account) {
                $balance = number_format($account->balance, 2);
                $accountHtml = "<div class='mb-1 text-primary'><strong>Acct Balance:</strong> ₦{$balance}</div>";
            }
        }

        return "
            <div style='font-size: 0.75rem; line-height: 1.2;'>
                <div class='mb-1'><strong>Payable:</strong> {$payable} &nbsp;|&nbsp; <strong>Claims:</strong> {$claims}</div>
                <div class='mb-1'><strong>Method:</strong> {$paymentMethod}</div>
                {$accountHtml}
                <div class='mb-1'><strong>Cashier:</strong> {$cashier}</div>
                <div class='text-muted'>{$time}</div>
                {$hmoHtml}
            </div>
        ";
    }

    protected function formatPaymentMethodWithChannel($payment)
    {
        $methodDisplay = $payment->payment_method ?: 'Unknown';
        $channel = '';
        $textClass = 'text-secondary';
        $icon = 'mdi-account-cash';
        
        $methodLower = strtolower($methodDisplay);
        if (str_contains($methodLower, 'transfer') || str_contains($methodLower, 'pos') || str_contains($methodLower, 'bank')) {
            if ($payment->bank) {
                $channel = 'Bank: ' . $payment->bank->name;
                $textClass = 'text-success fw-bold';
                $icon = 'mdi-bank';
            }
        } elseif (str_contains($methodLower, 'staff') || $payment->staffBill) {
            if ($payment->staffBill) {
                $staffUser = \App\Models\User::find($payment->staffBill->staff_user_id);
                if ($staffUser) {
                    $channel = 'Staff: ' . trim($staffUser->firstname . ' ' . $staffUser->surname);
                    $textClass = 'text-primary fw-bold';
                    $icon = 'mdi-account-tie';
                }
            }
        } elseif (str_contains($methodLower, 'org') || str_contains($methodLower, 'corporate') || $payment->organizationBill) {
            if ($payment->organizationBill) {
                $org = \App\Models\Organization::find($payment->organizationBill->organization_id);
                if ($org) {
                    $channel = 'Corporate: ' . $org->name;
                    $textClass = 'text-info fw-bold';
                    $icon = 'mdi-domain';
                }
            }
        } elseif ($payment->hmo_id) {
            $hmo = \App\Models\Hmo::with('scheme')->find($payment->hmo_id);
            if ($hmo) {
                $channel = 'HMO: ' . $hmo->name;
                if ($hmo->scheme) {
                    $channel .= ' (' . $hmo->scheme->name . ')';
                }
                $textClass = 'text-warning fw-bold';
                $icon = 'mdi-hospital-building';
            }
        }

        if ($channel) {
            return $methodDisplay . " <span class='d-block mt-1'><span class='{$textClass}' style='font-size:0.65rem;'><i class='mdi {$icon} me-1'></i>{$channel}</span></span>";
        }
        return $methodDisplay;
    }

    /**
     * Shared helper to apply Cashier, Payment Method, Bank, and Entity filters
     */
    protected function applyPaymentFilters($query, Request $request, $posrRelationPath = '')
    {
        $hasFilters = $request->filled('payment_method') || $request->filled('cashier_id') || $request->filled('bank_id') || $request->filled('entity');
        
        if (!$hasFilters) {
            return $query;
        }

        $applyPaymentSpecificFilters = function ($q) use ($request) {
            if ($request->filled('payment_method')) {
                $q->where('payment_method', $request->payment_method);
            }
            if ($request->filled('cashier_id')) {
                $q->where('user_id', $request->cashier_id);
            }
            if ($request->filled('bank_id')) {
                $q->where('bank_id', $request->bank_id);
            }
        };

        if ($request->filled('payment_method') || $request->filled('cashier_id') || $request->filled('bank_id')) {
            if ($posrRelationPath === 'self_payment') {
                $applyPaymentSpecificFilters($query);
            } elseif (!empty($posrRelationPath)) {
                $query->whereHas($posrRelationPath . '.payment', function ($q) use ($applyPaymentSpecificFilters) {
                    $applyPaymentSpecificFilters($q);
                });
            }
        }

        if ($request->filled('entity')) {
            $parts = explode(':', $request->entity);
            if (count($parts) === 2) {
                $type = $parts[0];
                $id = $parts[1];

                if ($posrRelationPath === 'self_payment') {
                    if ($type === 'ORG') {
                        $query->where(function($sub) use ($id) {
                            $sub->whereHas('organizationBills', fn($sq) => $sq->where('organization_id', $id))
                                ->orWhereHas('product_or_service_request', fn($sq) => $sq->where('organization_id', $id));
                        });
                    } elseif ($type === 'STAFF') {
                        $query->whereHas('staffBills', fn($sq) => $sq->where('staff_user_id', $id));
                    } elseif ($type === 'PATIENT') {
                        $query->where(function($sub) use ($id) {
                            $sub->where('patient_id', $id)
                                ->orWhereHas('product_or_service_request', fn($sq) => $sq->where('patient_id', $id));
                        });
                    }
                } else {
                    if ($type === 'PATIENT') {
                        $query->where('patient_id', $id);
                    } elseif ($type === 'ORG' && !empty($posrRelationPath)) {
                        $query->whereHas($posrRelationPath . '.payment', function ($q) use ($id) {
                            $q->whereHas('organizationBills', fn($sq) => $sq->where('organization_id', $id))
                              ->orWhereHas('product_or_service_request', fn($sq) => $sq->where('organization_id', $id));
                        });
                    } elseif ($type === 'STAFF' && !empty($posrRelationPath)) {
                        $query->whereHas($posrRelationPath . '.payment', function ($q) use ($id) {
                            $q->whereHas('staffBills', fn($sq) => $sq->where('staff_user_id', $id));
                        });
                    }
                }
            }
        }

        return $query;
    }

    /**
     * AJAX Endpoint for Entity Search
     */
    public function searchEntities(Request $request)
    {
        $q = $request->input('q');
        if (!$q) return response()->json(['results' => []]);

        $results = [];

        // 1. Organizations
        $orgs = \App\Models\Organization::where('name', 'like', "%{$q}%")
            ->limit(10)->get();
        if ($orgs->isNotEmpty()) {
            $orgOptions = [];
            foreach ($orgs as $org) {
                $orgOptions[] = ['id' => 'ORG:' . $org->id, 'text' => $org->name];
            }
            $results[] = ['text' => 'Organizations (Corporate)', 'children' => $orgOptions];
        }

        // 2. Staff (Users)
        $staff = \App\Models\User::with('staff')->where(function($query) use ($q) {
            $query->where('firstname', 'like', "%{$q}%")
                  ->orWhere('surname', 'like', "%{$q}%")
                  ->orWhereHas('staff', function($sq) use ($q) {
                      $sq->where('employee_id', 'like', "%{$q}%");
                  });
        })->where('is_admin', '!=', 19)->limit(10)->get();
        if ($staff->isNotEmpty()) {
            $staffOptions = [];
            foreach ($staff as $u) {
                $code = $u->staff && $u->staff->employee_id ? ' (' . $u->staff->employee_id . ')' : '';
                $staffOptions[] = ['id' => 'STAFF:' . $u->id, 'text' => trim($u->firstname . ' ' . $u->surname) . $code];
            }
            $results[] = ['text' => 'Staff / Users', 'children' => $staffOptions];
        }

        // 3. Patients
        $patients = \App\Models\Patient::with('user')->where(function($qBuilder) use ($q) {
            $qBuilder->whereHas('user', function($query) use ($q) {
                $query->where('firstname', 'like', "%{$q}%")
                      ->orWhere('surname', 'like', "%{$q}%");
            })->orWhere('file_no', 'like', "%{$q}%");
        })->limit(10)->get();
          
        if ($patients->isNotEmpty()) {
            $patOptions = [];
            foreach ($patients as $p) {
                $name = $p->user ? trim($p->user->firstname . ' ' . $p->user->surname) : 'Unknown';
                $patOptions[] = ['id' => 'PATIENT:' . $p->id, 'text' => $name . ' (' . $p->file_no . ')'];
            }
            $results[] = ['text' => 'Patients', 'children' => $patOptions];
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Generic Details Endpoint for OpsAudit Modal
     */
    protected function moduleRequisitionsData(Request $request, $storeRoleConfig = null)
    {
        $query = \App\Models\StoreRequisition::with([
            'fromStore',
            'toStore',
            'requester',
            'approver',
            'fulfiller',
            'items.product.price',
            'items.sourceBatch'
        ]);

        $this->applyDateFilter($query, $request);

        if ($request->filled('from_store_id')) $query->where('from_store_id', $request->from_store_id);
        if ($request->filled('to_store_id')) $query->where('to_store_id', $request->to_store_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('requested_by')) $query->where('requested_by', $request->requested_by);

        if ($storeRoleConfig) {
            $query->where(function ($q) use ($storeRoleConfig) {
                $q->whereHas('fromStore', function ($sub) use ($storeRoleConfig) {
                    if (!empty($storeRoleConfig['roles'])) {
                        $sub->whereIn('distribution_role', $storeRoleConfig['roles']);
                    }
                    if (!empty($storeRoleConfig['name_match'])) {
                        $sub->where(function ($nameSub) use ($storeRoleConfig) {
                            foreach ($storeRoleConfig['name_match'] as $match) {
                                $nameSub->orWhere('store_name', 'like', $match);
                            }
                        });
                    }
                })->orWhereHas('toStore', function ($sub) use ($storeRoleConfig) {
                    if (!empty($storeRoleConfig['roles'])) {
                        $sub->whereIn('distribution_role', $storeRoleConfig['roles']);
                    }
                    if (!empty($storeRoleConfig['name_match'])) {
                        $sub->where(function ($nameSub) use ($storeRoleConfig) {
                            foreach ($storeRoleConfig['name_match'] as $match) {
                                $nameSub->orWhere('store_name', 'like', $match);
                            }
                        });
                    }
                });
            });
        }

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $statusColors = ['pending' => 'warning text-dark', 'approved' => 'info', 'fulfilled' => 'success', 'rejected' => 'danger', 'partial' => 'warning'];
            $sColor = $statusColors[$row->status] ?? 'secondary';

            $getCost = fn($i) => ($i->sourceBatch && (float)$i->sourceBatch->cost_price > 0) ? (float)$i->sourceBatch->cost_price : (float)($i->product->price->pr_buy_price ?? 0);

            $reqValue = $row->items ? $row->items->sum(fn($i) => $i->status !== 'rejected' ? (($i->requested_qty ?? 0) * $getCost($i)) : 0) : 0;
            $apprValue = $row->items ? $row->items->sum(fn($i) => $i->status !== 'rejected' ? (($i->approved_qty ?? 0) * $getCost($i)) : 0) : 0;
            $fulValue = $row->items ? $row->items->sum(fn($i) => $i->status !== 'rejected' ? (($i->fulfilled_qty ?? 0) * $getCost($i)) : 0) : 0;
            $rejValue = $row->items ? $row->items->sum(fn($i) => $i->status === 'rejected' ? (($i->requested_qty ?? 0) * $getCost($i)) : 0) : 0;

            return [
                'date' => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d M Y') : '-',
                'req_no' => $row->requisition_number ?? '-',
                'from_store' => $row->fromStore ? ($row->fromStore->store_name . '<br><small class="text-muted">' . $row->fromStore->distributionRoleLabel() . '</small>') : '-',
                'to_store' => $row->toStore ? ($row->toStore->store_name . '<br><small class="text-muted">' . $row->toStore->distributionRoleLabel() . '</small>') : '-',
                'status' => '<span class="badge bg-' . $sColor . '">' . ucfirst($row->status ?? '-') . '</span>',
                'requested_by' => $row->requester?->firstname ? ($row->requester->firstname . ' ' . ($row->requester->surname ?? '')) : '-',
                'approved_by' => $row->approver?->firstname ? ($row->approver->firstname . ' ' . ($row->approver->surname ?? '')) : '-',
                'fulfilled_by' => $row->fulfiller?->firstname ? ($row->fulfiller->firstname . ' ' . ($row->fulfiller->surname ?? '')) : '-',
                'items_count' => $row->items ? $row->items->count() : '-',
                'req_value' => '₦' . number_format($reqValue, 2),
                'appr_value' => '₦' . number_format($apprValue, 2),
                'ful_value' => '₦' . number_format($fulValue, 2),
                'rej_value' => '₦' . number_format($rejValue, 2),
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'StoreRequisition'),
            ];
        }, function ($kpiQuery) {
            $all = $kpiQuery->get();
            $getCost = fn($i) => ($i->sourceBatch && (float)$i->sourceBatch->cost_price > 0) ? (float)$i->sourceBatch->cost_price : (float)($i->product->price->pr_buy_price ?? 0);
            
            $totalReqCost = $all->sum(function($req) use ($getCost) {
                return $req->items ? $req->items->sum(fn($i) => $i->status !== 'rejected' ? (($i->requested_qty ?? 0) * $getCost($i)) : 0) : 0;
            });
            $totalApprCost = $all->sum(function($req) use ($getCost) {
                return $req->items ? $req->items->sum(fn($i) => $i->status !== 'rejected' ? (($i->approved_qty ?? 0) * $getCost($i)) : 0) : 0;
            });
            $totalFulCost = $all->sum(function($req) use ($getCost) {
                return $req->items ? $req->items->sum(fn($i) => $i->status !== 'rejected' ? (($i->fulfilled_qty ?? 0) * $getCost($i)) : 0) : 0;
            });
            $totalRejCost = $all->sum(function($req) use ($getCost) {
                return $req->items ? $req->items->sum(fn($i) => $i->status === 'rejected' ? (($i->requested_qty ?? 0) * $getCost($i)) : 0) : 0;
            });
            return [
                ['label' => 'Total Requisitions', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
                ['label' => 'Req Value (Cost)', 'value' => '₦' . number_format($totalReqCost, 2), 'color' => '#17a2b8'],
                ['label' => 'Appr Value (Cost)', 'value' => '₦' . number_format($totalApprCost, 2), 'color' => '#ffc107'],
                ['label' => 'Ful Value (Cost)', 'value' => '₦' . number_format($totalFulCost, 2), 'color' => '#28a745'],
                ['label' => 'Rej Value (Cost)', 'value' => '₦' . number_format($totalRejCost, 2), 'color' => '#dc3545'],
            ];
        }, $kpiQuery);
    }

    public function getRecordDetails($type, $id)
    {
        if ($type === 'admission') {
            return $this->getAdmissionDetails($id);
        } elseif ($type === 'requisition') {
            return $this->getRequisitionDetails($id);
        }

        return response()->json([
            'html' => '<div class="alert alert-warning m-3">Details view for type "' . e($type) . '" is not implemented yet.</div>',
            'title' => '<i class="mdi mdi-alert me-2 text-warning"></i> Unsupported Type'
        ]);
    }

    private function getAdmissionDetails($id)
    {
        $controller = new \App\Http\Controllers\AdmissionModuleController();
        $response = $controller->getAdmissionDetail($id);
        
        if ($response->status() !== 200) {
            return response()->json([
                'html' => '<div class="alert alert-danger m-3">Error fetching admission details.</div>',
                'title' => 'Error'
            ]);
        }

        $data = json_decode($response->getContent(), true);

        return response()->json([
            'html' => view('admin.ops_audit.details.admission', ['data' => $data])->render(),
            'title' => '<i class="mdi mdi-bed me-2 text-primary"></i> Admission Details — ' . ($data['patient_name'] ?? 'Unknown')
        ]);
    }

    private function getRequisitionDetails($id)
    {
        $requisition = \App\Models\StoreRequisition::with([
            'items.product.packagings', 
            'items.product.price',
            'items.sourceBatch',
            'fromStore', 
            'toStore', 
            'requester', 
            'approver', 
            'fulfiller'
        ])->find($id);

        if (!$requisition) {
            return response()->json([
                'html' => '<div class="alert alert-danger m-3">Requisition not found.</div>',
                'title' => 'Error'
            ]);
        }

        return response()->json([
            'html' => view('admin.ops_audit.details.requisition', compact('requisition'))->render(),
            'title' => '<i class="mdi mdi-swap-horizontal me-2 text-primary"></i> Requisition Details — ' . $requisition->requisition_number
        ]);
    }
}
