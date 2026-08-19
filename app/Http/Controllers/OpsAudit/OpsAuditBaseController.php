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
        if (!$user) return '<span class="text-muted">-</span>';
        $name = trim(($user->firstname ?? '') . ' ' . ($user->surname ?? ''));
        $fileNo = $patient?->file_no ?? 'N/A';
        $html = '<div class="font-weight-bold text-dark" style="font-size:0.82rem;"><i class="mdi mdi-account text-primary me-1"></i>' . e($name) . ' <span class="badge bg-light text-dark border" style="font-size:0.7rem;">#' . e($fileNo) . '</span></div>';
        if ($hmo) {
            $html .= '<small class="text-info font-weight-bold" style="font-size:0.72rem;"><i class="mdi mdi-hospital-building me-1"></i>' . e($hmo->name ?? '') . ($hmo->scheme ? ' (' . e($hmo->scheme->name) . ')' : '') . '</small>';
        }
        return $html;
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
            $activeQuery = (object)['auditor' => (object)['name' => 'Auditor'], 'query_notes' => $record->query_notes ?? 'Flagged'];
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

        if ($activeQuery) {
            $auditorName = $activeQuery->auditor->name ?? 'Auditor';
            $notes = htmlspecialchars($activeQuery->query_notes ?? 'Flagged', ENT_QUOTES);
            $html .= '<button class="btn btn-sm btn-warning text-dark font-weight-bold shadow-sm px-2 py-1 text-nowrap" onclick="openResolveQueryModal(\'' . addslashes($fullModelClass) . '\', ' . $record->id . ')" title="Queried: ' . $notes . '"><i class="mdi mdi-alert-circle me-1"></i> Resolve</button>';
        } else {
            if ($latestAudit) {
                $html .= '<button class="btn btn-sm btn-success disabled px-2 py-1 text-nowrap"><i class="mdi mdi-check-decagram me-1"></i> Audited</button>';
            } else {
                $html .= '<button class="btn btn-sm btn-outline-success audit-tick-btn px-2 py-1 text-nowrap" onclick="markAudited(this, \'' . addslashes($fullModelClass) . '\', ' . $record->id . ')"><i class="mdi mdi-check me-1"></i> Stamp</button>';
            }
            $html .= '<button class="btn btn-sm btn-outline-warning px-2 py-1 text-nowrap" onclick="openRaiseQueryModal(\'' . addslashes($fullModelClass) . '\', ' . $record->id . ')"><i class="mdi mdi-flag me-1"></i> Flag</button>';
        }
        $html .= '</div>';

        if ($activeQuery) {
            $html .= '<small class="d-block text-danger font-weight-bold mt-1" style="font-size:0.72rem;"><i class="mdi mdi-alert-circle me-1"></i>Active Query</small>';
        } elseif ($latestAudit) {
            $stampedTime = (isset($latestAudit->created_at) && is_object($latestAudit->created_at)) ? $latestAudit->created_at->diffForHumans() : 'Recently';
            $html .= '<small class="d-block text-success mt-1" style="font-size:0.72rem;"><i class="mdi mdi-check-all me-1"></i>Stamped ' . $stampedTime . '</small>';
        }

        return $html;
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
     * Shared helper to apply Cashier and Payment Method filters
     */
    protected function applyPaymentFilters($query, Request $request, $posrRelationPath = '')
    {
        if (!$request->filled('payment_method') && !$request->filled('cashier_id')) {
            return $query;
        }

        // If the model is a Payment
        if ($posrRelationPath === 'self_payment') {
            if ($request->filled('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }
            if ($request->filled('cashier_id')) {
                $query->where('user_id', $request->cashier_id);
            }
            return $query;
        }

        // Otherwise, it relates to POSR which relates to Payment
        $paymentRel = $posrRelationPath ? $posrRelationPath . '.payment' : 'payment';

        return $query->whereHas($paymentRel, function ($q) use ($request) {
            if ($request->filled('payment_method')) {
                $q->where('payment_method', $request->payment_method);
            }
            if ($request->filled('cashier_id')) {
                $q->where('user_id', $request->cashier_id);
            }
        });
    }
}
