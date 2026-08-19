<?php

namespace App\Http\Controllers\OpsAudit;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\HmoClaim;
use App\Models\ProductOrServiceRequest;
use App\Models\HmoRemittance;
use App\Models\AuditMark;

class OpsAuditHmoController extends OpsAuditBaseController
{
    public function index(Request $request)
    {
        $hmos = \App\Models\Hmo::with('scheme')->orderBy('name')->get()->groupBy(fn($hmo) => $hmo->scheme ? $hmo->scheme->name : 'Other Schemes');
        $hmoSchemes = \App\Models\HmoScheme::orderBy('name')->pluck('name', 'id');
        $users = \App\Models\User::role(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER'])->orderBy('firstname')->get()->mapWithKeys(fn($u) => [$u->id => trim($u->firstname . ' ' . ($u->othername ?? '') . ' ' . $u->surname)]);
        $banks = \App\Models\Bank::orderBy('name')->pluck('name', 'id'); // Assuming Bank model exists

        return view('admin.ops_audit.hmo', compact('hmos', 'hmoSchemes', 'users', 'banks'));
    }

    public function data(Request $request, $tab)
    {
        if ($request->isMethod('post') && in_array($request->action, ['bulk_stamp_preview', 'bulk_stamp'])) {
            return $this->handleBulkStamp($request, $tab);
        }

        switch ($tab) {
            case 'claims':
                return $this->claimsData($request);
            case 'coverage':
                return $this->coverageData($request);
            case 'remittances':
                return $this->remittancesData($request);
            default:
                return response()->json(['error' => 'Invalid tab'], 400);
        }
    }

    protected function claimsData(Request $request)
    {
        $query = HmoClaim::with([
            'patient.user',
            'patient.hmo.scheme',
            'hmo',
            'createdBy',
            'processedBy',
            'payment.user' // cashier
        ]);

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);

        if ($request->filled('hmo_id')) $query->where('hmo_id', $request->hmo_id);
        if ($request->filled('hmo_scheme_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('hmo_scheme_id', $request->hmo_scheme_id));
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('created_by')) $query->where('created_by', $request->created_by);
        if ($request->filled('processed_by')) $query->where('processed_by', $request->processed_by);
        if ($request->filled('cashier_id')) $query->whereHas('payment', fn($q) => $q->where('user_id', $request->cashier_id));

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $row->hmo ?? $patient?->hmo;
            $payment = $row->payment;
            $cashier = $payment?->user;

            $statusColors = ['pending' => 'warning text-dark', 'approved' => 'info', 'rejected' => 'danger', 'paid' => 'success'];
            $sColor = $statusColors[$row->status] ?? 'secondary';

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'claims_amount' => '₦' . number_format($row->claims_amount ?? 0, 2),
                'status' => '<span class="badge bg-' . $sColor . ' font-weight-bold">' . ucfirst($row->status ?? '-') . '</span>',
                'created_by' => $row->createdBy?->firstname ? ($row->createdBy->firstname . ' ' . ($row->createdBy->surname ?? '')) : '-',
                'processed_by' => $row->processedBy?->firstname ? ($row->processedBy->firstname . ' ' . ($row->processedBy->surname ?? '')) : '-',
                'payment_ref' => $row->payment_reference ?? '-',
                'cashier' => $cashier?->firstname ? ($cashier->firstname . ' ' . ($cashier->surname ?? '')) : '-',
                'pay_status' => $payment ? '<span class="badge bg-success">Paid</span>' : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'HmoClaim'),
            ];
        }, function ($kpiQuery) {
            $processed = (clone $kpiQuery)->where('processed_at', '!=', null);
            return [
                ['label' => 'Total Claims', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Pending', 'value' => number_format((clone $kpiQuery)->where('status', 'pending')->count()), 'color' => '#ffc107'],
                ['label' => 'Approved', 'value' => number_format((clone $kpiQuery)->where('status', 'approved')->count()), 'color' => '#0dcaf0'],
                ['label' => 'Rejected', 'value' => number_format((clone $kpiQuery)->where('status', 'rejected')->count()), 'color' => '#dc3545'],
                ['label' => 'Paid', 'value' => number_format((clone $kpiQuery)->where('status', 'paid')->count()), 'color' => '#198754'],
                ['label' => 'Total Claims Amount', 'value' => '₦' . number_format((clone $kpiQuery)->sum('claims_amount'), 2), 'color' => '#6610f2'],
                ['label' => 'Recovered', 'value' => '₦' . number_format((clone $kpiQuery)->where('status', 'paid')->sum('claims_amount'), 2), 'color' => '#198754'],
                ['label' => 'Avg Processing (hrs)', 'value' => (clone $processed)->count() > 0 ? round((clone $processed)->avg(\Illuminate\Support\Facades\DB::raw('TIMESTAMPDIFF(HOUR, created_at, processed_at)'))) : '-', 'color' => '#6c757d'],
            ];
        }, $kpiQuery);
    }

    protected function coverageData(Request $request)
    {
        $query = ProductOrServiceRequest::with([
            'patient.user',
            'patient.hmo.scheme',
            'payment.user',
            'validatedBy',
            'product',
            'service'
        ])->whereNotNull('hmo_id'); // Ensure it's HMO related

        $this->applyDateFilter($query, $request);
        $this->applyShiftFilter($query, $request);

        if ($request->filled('hmo_id')) $query->where('hmo_id', $request->hmo_id);
        if ($request->filled('hmo_scheme_id')) $query->whereHas('patient.hmo', fn($q) => $q->where('hmo_scheme_id', $request->hmo_scheme_id));
        if ($request->filled('coverage_mode')) $query->where('coverage_mode', $request->coverage_mode);
        if ($request->filled('validation_status')) $query->where('validation_status', $request->validation_status);
        if ($request->filled('validated_by')) $query->where('validated_by', $request->validated_by);
        if ($request->filled('type')) $query->where('type', $request->type);
        if ($request->filled('has_auth_code')) {
            if ($request->has_auth_code == 'yes') {
                $query->whereNotNull('auth_code');
            } else {
                $query->whereNull('auth_code');
            }
        }

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            $patient = $row->patient;
            $user = $patient?->user;
            $hmo = $patient?->hmo;
            $payment = $row->payment;
            $cashier = $payment?->user;
            $item = $row->product ? $row->product->name : ($row->service ? $row->service->name : '-');

            $vStatusMap = ['pending' => 'warning text-dark', 'validated' => 'success', 'rejected' => 'danger'];
            $vColor = $vStatusMap[$row->validation_status] ?? 'secondary';

            return [
                'date' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-',
                'patient' => $this->renderPatient($user, $patient, $hmo),
                'hmo' => $this->renderHmo($hmo),
                'coverage_mode' => '<span class="badge bg-light text-dark border">' . ucfirst($row->coverage_mode ?? 'None') . '</span>',
                'type' => ucfirst($row->type ?? '-'),
                'item' => $item,
                'qty' => $row->qty ?? 1,
                'amount' => '₦' . number_format($row->amount ?? 0, 2),
                'payable' => '₦' . number_format($row->payable_amount ?? 0, 2),
                'claims' => '₦' . number_format($row->claims_amount ?? 0, 2),
                'auth_code' => $row->auth_code ?? '-',
                'validated_by' => $row->validatedBy?->firstname ? ($row->validatedBy->firstname . ' ' . ($row->validatedBy->surname ?? '')) : '-',
                'validation_status' => '<span class="badge bg-' . $vColor . '">' . ucfirst($row->validation_status ?? '-') . '</span>',
                'cashier' => $cashier?->firstname ? ($cashier->firstname . ' ' . ($cashier->surname ?? '')) : '-',
                'method' => $payment?->payment_method ? '<span class="badge bg-light text-dark border">' . $payment->payment_method . '</span>' : '-',
                'pay_status' => $payment ? '<span class="badge bg-success">Paid</span>' : '<span class="badge bg-warning text-dark">Unpaid</span>',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'ProductOrServiceRequest'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Items', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Express', 'value' => number_format((clone $kpiQuery)->where('coverage_mode', 'express')->count()), 'color' => '#198754'],
                ['label' => 'Primary', 'value' => number_format((clone $kpiQuery)->where('coverage_mode', 'primary')->count()), 'color' => '#0dcaf0'],
                ['label' => 'Secondary', 'value' => number_format((clone $kpiQuery)->where('coverage_mode', 'secondary')->count()), 'color' => '#ffc107'],
                ['label' => 'Pending Vetting', 'value' => number_format((clone $kpiQuery)->where('validation_status', 'pending')->count()), 'color' => '#dc3545'],
                ['label' => 'Validated', 'value' => number_format((clone $kpiQuery)->where('validation_status', 'validated')->count()), 'color' => '#198754'],
                ['label' => 'Total Claims Value', 'value' => '₦' . number_format((clone $kpiQuery)->sum('claims_amount'), 2), 'color' => '#6610f2'],
                ['label' => 'Total Payable', 'value' => '₦' . number_format((clone $kpiQuery)->sum('payable_amount'), 2), 'color' => '#0d6efd'],
            ];
        }, $kpiQuery);
    }

    protected function remittancesData(Request $request)
    {
        $query = HmoRemittance::with([
            'hmo',
            'bank',
            'createdBy'
        ]);

        $this->applyDateFilter($query, $request, 'payment_date');

        if ($request->filled('hmo_id')) $query->where('hmo_id', $request->hmo_id);
        if ($request->filled('bank_id')) $query->where('bank_id', $request->bank_id);
        if ($request->filled('payment_method')) $query->where('payment_method', $request->payment_method);
        if ($request->filled('created_by')) $query->where('created_by', $request->created_by);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse($query, $request, fn($q) => $q, function ($row) {
            return [
                'payment_date' => $row->payment_date ? Carbon::parse($row->payment_date)->format('d M Y') : '-',
                'hmo' => $this->renderHmo($row->hmo),
                'period' => ($row->period_from ? Carbon::parse($row->period_from)->format('d M y') : '?') . ' - ' . ($row->period_to ? Carbon::parse($row->period_to)->format('d M y') : '?'),
                'amount_remitted' => '₦' . number_format($row->amount ?? 0, 2),
                'ref_no' => $row->reference_number ?? '-',
                'payment_method' => $row->payment_method ?? '-',
                'bank' => $row->bank?->name ?? $row->bank_name ?? '-',
                'created_by' => $row->createdBy?->firstname ? ($row->createdBy->firstname . ' ' . ($row->createdBy->surname ?? '')) : '-',
                'payment_info' => $this->renderPaymentInfo($row),
                'audit' => $this->renderAuditAction($row, 'HmoRemittance'),
            ];
        }, function ($kpiQuery) {
            return [
                ['label' => 'Total Remittances', 'value' => number_format((clone $kpiQuery)->count()), 'color' => '#0d6efd'],
                ['label' => 'Total Remitted Amount', 'value' => '₦' . number_format((clone $kpiQuery)->sum('amount'), 2), 'color' => '#198754'],
                ['label' => 'Avg Remittance', 'value' => (clone $kpiQuery)->count() > 0 ? '₦' . number_format((clone $kpiQuery)->avg('amount'), 2) : '-', 'color' => '#0dcaf0'],
            ];
        }, $kpiQuery);
    }

    protected function handleBulkStamp(Request $request, $tab)
    {
        $modelMap = [
            'claims' => HmoClaim::class,
            'coverage' => ProductOrServiceRequest::class,
            'remittances' => HmoRemittance::class,
        ];
        
        $request->merge(['zone_key' => 'ops_audit.hmo.' . $tab]);
        return $this->processBulkStamp($request, $tab, $modelMap);
    }
}
