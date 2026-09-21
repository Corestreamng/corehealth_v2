<?php

namespace App\Http\Controllers\EncounterWorkbench\Tabs;

use App\Http\Controllers\EncounterWorkbench\EncounterWorkbenchBaseController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Tab 5 — Revenue & Billing Intelligence
 * Reads billing data (read-only SELECT) from product_or_service_requests + payments.
 * Admin / Accounts: see all.
 * Doctors: see own encounter revenue only.
 */
class RevenueBillingTab extends EncounterWorkbenchBaseController
{
    public function getData(Request $request): JsonResponse
    {
        if (!$this->canViewRevenue()) {
            abort(403, 'Access denied.');
        }

        $start = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->subDays(30)->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        $groupBy = $request->input('group_by', 'doctor'); // doctor | clinic | hmo | method

        $data = match ($groupBy) {
            'clinic' => $this->revenueByClinic($start, $end, $request),
            'hmo' => $this->revenueByHmo($start, $end, $request),
            'method' => $this->revenueByMethod($start, $end, $request),
            default => $this->revenueByDoctor($start, $end, $request),
        };

        // Top-level KPIs across all groups
        $totals = $this->getTotals($start, $end, $request);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => count($data['rows']),
            'recordsFiltered' => count($data['rows']),
            'data' => $data['rows'],
            'kpis' => [
                $this->kpiCard('Total Billed', '₦' . number_format($totals->total_billed, 2), '#0d6efd', 'mdi-cash-multiple'),
                $this->kpiCard('Total Payable', '₦' . number_format($totals->total_payable, 2), '#17a2b8', 'mdi-cash-check'),
                $this->kpiCard('HMO Claims', '₦' . number_format($totals->hmo_claims, 2), '#6610f2', 'mdi-shield-account'),
                $this->kpiCard('Encounters w/ Billing', number_format($totals->enc_with_billing), '#198754', 'mdi-receipt'),
                $this->kpiCard('Avg Bill / Encounter', '₦' . number_format($totals->enc_with_billing > 0 ? $totals->total_billed / $totals->enc_with_billing : 0, 2), '#fd7e14', 'mdi-chart-bar'),
            ],
            'chart_data' => $data['chart'],
        ]);
    }

    // ─── Breakdowns ───────────────────────────────────────────────────────────

    private function revenueByDoctor(Carbon $start, Carbon $end, Request $request): array
    {
        $rows = DB::table('product_or_service_requests as posr')
            ->join('encounters as e', 'posr.encounter_id', '=', 'e.id')
            ->join('users as u', 'e.doctor_id', '=', 'u.id')
            ->leftJoin('doctor_queues as dq', 'e.queue_id', '=', 'dq.id')
            ->leftJoin('clinics as cl', 'dq.clinic_id', '=', 'cl.id')
            ->leftJoin('services as s', 'posr.service_id', '=', 's.id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->when(!$this->canViewAnalytics() && $this->userIsDoctor(), fn ($q) => $q->where('e.doctor_id', Auth::id()))
            ->when($request->filled('clinic_id'), fn ($q) => $q->where('dq.clinic_id', $request->clinic_id))
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('e.doctor_id', $request->doctor_id))
            ->selectRaw('
                u.id as group_id,
                CONCAT(COALESCE(u.surname,""), " ", COALESCE(u.firstname,""), " ", COALESCE(u.othername,"")) as label,
                MIN(cl.name) as clinic,
                COUNT(DISTINCT e.patient_id) as unique_patients,
                COUNT(DISTINCT e.id) as encounters,
                COUNT(DISTINCT posr.id) as line_items,
                COALESCE(SUM(posr.amount), 0) as total_billed,
                COALESCE(SUM(posr.payable_amount), 0) as total_payable,
                COALESCE(SUM(posr.claims_amount), 0) as hmo_claims,
                COALESCE(SUM(CASE WHEN s.category_id = 1 THEN posr.amount ELSE 0 END), 0) as consult_billed,
                COALESCE(SUM(CASE WHEN s.category_id = 1 THEN posr.payable_amount ELSE 0 END), 0) as consult_payable,
                COALESCE(SUM(CASE WHEN s.category_id = 1 THEN posr.claims_amount ELSE 0 END), 0) as consult_claims
            ')
            ->groupBy('u.id', 'u.surname', 'u.firstname', 'u.othername')
            ->orderByDesc('total_billed')
            ->get();

        return $this->formatRevenueRows($rows, 'doctor', true);
    }

    private function revenueByClinic(Carbon $start, Carbon $end, Request $request): array
    {
        $rows = DB::table('product_or_service_requests as posr')
            ->join('encounters as e', 'posr.encounter_id', '=', 'e.id')
            ->join('doctor_queues as dq', 'e.queue_id', '=', 'dq.id')
            ->join('clinics as cl', 'dq.clinic_id', '=', 'cl.id')
            ->leftJoin('services as s', 'posr.service_id', '=', 's.id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->when($request->filled('clinic_id'), fn ($q) => $q->where('dq.clinic_id', $request->clinic_id))
            ->selectRaw('
                cl.id as group_id,
                cl.name as label,
                NULL as clinic,
                COUNT(DISTINCT e.patient_id) as unique_patients,
                COUNT(DISTINCT e.id) as encounters,
                COUNT(DISTINCT posr.id) as line_items,
                COALESCE(SUM(posr.amount), 0) as total_billed,
                COALESCE(SUM(posr.payable_amount), 0) as total_payable,
                COALESCE(SUM(posr.claims_amount), 0) as hmo_claims,
                COALESCE(SUM(CASE WHEN s.category_id = 1 THEN posr.amount ELSE 0 END), 0) as consult_billed,
                COALESCE(SUM(CASE WHEN s.category_id = 1 THEN posr.payable_amount ELSE 0 END), 0) as consult_payable,
                COALESCE(SUM(CASE WHEN s.category_id = 1 THEN posr.claims_amount ELSE 0 END), 0) as consult_claims
            ')
            ->groupBy('cl.id', 'cl.name')
            ->orderByDesc('total_billed')
            ->get();

        return $this->formatRevenueRows($rows, 'clinic');
    }

    private function revenueByHmo(Carbon $start, Carbon $end, Request $request): array
    {
        $rows = DB::table('product_or_service_requests as posr')
            ->join('encounters as e', 'posr.encounter_id', '=', 'e.id')
            ->join('patients as p', 'e.patient_id', '=', 'p.id')
            ->leftJoin('hmos as h', 'p.hmo_id', '=', 'h.id')
            ->leftJoin('services as s', 'posr.service_id', '=', 's.id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->when($request->filled('hmo_id'), fn ($q) => $q->where('p.hmo_id', $request->hmo_id))
            ->selectRaw('
                COALESCE(h.id, 0) as group_id,
                COALESCE(h.name, "Private / Self-pay") as label,
                NULL as clinic,
                COUNT(DISTINCT e.patient_id) as unique_patients,
                COUNT(DISTINCT e.id) as encounters,
                COUNT(DISTINCT posr.id) as line_items,
                COALESCE(SUM(posr.amount), 0) as total_billed,
                COALESCE(SUM(posr.payable_amount), 0) as total_payable,
                COALESCE(SUM(posr.claims_amount), 0) as hmo_claims,
                COALESCE(SUM(CASE WHEN s.category_id = 1 THEN posr.amount ELSE 0 END), 0) as consult_billed,
                COALESCE(SUM(CASE WHEN s.category_id = 1 THEN posr.payable_amount ELSE 0 END), 0) as consult_payable,
                COALESCE(SUM(CASE WHEN s.category_id = 1 THEN posr.claims_amount ELSE 0 END), 0) as consult_claims
            ')
            ->groupBy('h.id', 'h.name')
            ->orderByDesc('total_billed')
            ->get();

        return $this->formatRevenueRows($rows, 'hmo');
    }

    private function revenueByMethod(Carbon $start, Carbon $end, Request $request): array
    {
        $rows = DB::table('payments as pay')
            ->join('product_or_service_requests as posr', function ($j) {
                $j->on('posr.id', '=', 'pay.service_request_id')
                    ->orWhereRaw('JSON_CONTAINS(pay.product_or_service_request_ids, CAST(posr.id AS JSON), "$")');
            })
            ->join('encounters as e', 'posr.encounter_id', '=', 'e.id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->selectRaw('
                pay.payment_method as group_id,
                COALESCE(pay.payment_method, "Unknown") as label,
                NULL as clinic,
                COUNT(DISTINCT e.patient_id) as unique_patients,
                COUNT(DISTINCT e.id) as encounters,
                COUNT(DISTINCT pay.id) as line_items,
                COALESCE(SUM(pay.total), 0) as total_billed,
                COALESCE(SUM(pay.total), 0) as total_payable,
                0 as hmo_claims,
                0 as consult_billed,
                0 as consult_payable,
                0 as consult_claims
            ')
            ->groupBy('pay.payment_method')
            ->orderByDesc('total_billed')
            ->get();

        return $this->formatRevenueRows($rows, 'method');
    }

    // ─── Format helpers ───────────────────────────────────────────────────────

    private function formatRevenueRows($rows, string $type, bool $showClinic = false): array
    {
        $tableRows = $rows->map(function ($row) use ($type, $showClinic) {
            $avgPerEnc = $row->encounters > 0 ? round($row->total_billed / $row->encounters, 2) : 0;
            $hmoShare = $row->total_billed > 0 ? round($row->hmo_claims / $row->total_billed * 100, 1) : 0;

            $rowData = [
                'group_id' => $row->group_id ?? null,
                'label' => '<strong>' . e($row->label) . '</strong>',
                'unique_patients' => number_format($row->unique_patients),
                'encounters' => number_format($row->encounters),
                'line_items' => number_format($row->line_items),
                'total_billed' => '<span class="text-primary font-weight-bold">₦' . number_format($row->total_billed, 2) . '</span>',
                'consult_billed' => '₦' . number_format($row->consult_billed ?? 0, 2),
                'consult_payable' => '₦' . number_format($row->consult_payable ?? 0, 2),
                'consult_claims' => '₦' . number_format($row->consult_claims ?? 0, 2),
                'total_payable' => '₦' . number_format($row->total_payable, 2),
                'hmo_claims' => '₦' . number_format($row->hmo_claims, 2),
                'hmo_share' => '<span class="badge bg-info">' . $hmoShare . '%</span>',
                'avg_per_enc' => '₦' . number_format($avgPerEnc, 2),
            ];

            if ($showClinic) {
                $rowData['clinic'] = e($row->clinic ?? '—');
            }

            return $rowData;
        })->values()->toArray();

        return [
            'rows' => $tableRows,
            'chart' => [
                'labels' => $rows->pluck('label')->toArray(),
                'billed' => $rows->pluck('total_billed')->map(fn ($v) => (float)$v)->toArray(),
                'hmo' => $rows->pluck('hmo_claims')->map(fn ($v) => (float)$v)->toArray(),
            ],
        ];
    }

    private function getTotals(Carbon $start, Carbon $end, Request $request): object
    {
        return DB::table('product_or_service_requests as posr')
            ->join('encounters as e', 'posr.encounter_id', '=', 'e.id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->when(!$this->canViewAnalytics() && $this->userIsDoctor(), fn ($q) => $q->where('e.doctor_id', Auth::id()))
            ->when($request->filled('clinic_id'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('doctor_queues as dq')
                    ->whereColumn('dq.id', 'e.queue_id')
                    ->where('dq.clinic_id', $request->clinic_id);
            }))
            ->selectRaw('
                COALESCE(SUM(posr.amount), 0) as total_billed,
                COALESCE(SUM(posr.payable_amount), 0) as total_payable,
                COALESCE(SUM(posr.claims_amount), 0) as hmo_claims,
                COUNT(DISTINCT e.id) as enc_with_billing
            ')
            ->first() ?? (object) ['total_billed' => 0, 'total_payable' => 0, 'hmo_claims' => 0, 'enc_with_billing' => 0];
    }
}
