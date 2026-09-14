<?php

namespace App\Http\Controllers\EncounterWorkbench\Tabs;

use App\Http\Controllers\EncounterWorkbench\EncounterWorkbenchBaseController;
use App\Models\Clinic;
use App\Models\Encounter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tab 2 — KPI Overview
 * Lightweight aggregate endpoint used by the top KPI strip AND the overview tab.
 * Accessible by all workbench roles (role-scoped).
 */
class EncounterKpiTab extends EncounterWorkbenchBaseController
{
    public function getData(Request $request): JsonResponse
    {
        $base = $this->buildBaseQuery($request);

        $total = (clone $base)->count();
        $completed = (clone $base)->where('completed', true)->count();
        $active = $total - $completed;

        $uniquePatients = (clone $base)->distinct('patient_id')->count('patient_id');

        $newPatients = (clone $base)->whereHas('patient', function ($pq) {
            $pq->whereHas('encounters', null, '=', 1);
        })->count();

        $avgDurationSec = (clone $base)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as avg_min')
            ->value('avg_min');

        $avgDuration = $avgDurationSec !== null
            ? round($avgDurationSec) . ' min'
            : '—';

        // Daily trend: encounters per day over the period
        $dailyTrend = (clone $base)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total, SUM(completed) as completed')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day' => Carbon::parse($r->day)->format('d M'),
                'total' => (int) $r->total,
                'completed' => (int) $r->completed,
                'active' => (int) ($r->total - $r->completed),
            ]);

        // Clinic distribution (top 10)
        $clinicDist = (clone $base)
            ->join('doctor_queues as dq', 'encounters.queue_id', '=', 'dq.id')
            ->join('clinics as cl', 'dq.clinic_id', '=', 'cl.id')
            ->selectRaw('cl.name as clinic_name, COUNT(*) as total')
            ->groupBy('cl.id', 'cl.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // HMO vs Private breakdown
        $hmoCount = (clone $base)->whereHas('patient', fn ($q) => $q->whereNotNull('hmo_id'))->count();
        $privateCount = $total - $hmoCount;

        return response()->json([
            'kpis' => [
                $this->kpiCard('Total Encounters', number_format($total), '#0d6efd', 'mdi-stethoscope'),
                $this->kpiCard('Active', number_format($active), '#17a2b8', 'mdi-account-clock'),
                $this->kpiCard('Completed', number_format($completed), '#198754', 'mdi-check-circle-outline'),
                $this->kpiCard('Unique Patients', number_format($uniquePatients), '#6610f2', 'mdi-account-group'),
                $this->kpiCard('New Patients', number_format($newPatients), '#fd7e14', 'mdi-account-plus-outline'),
                $this->kpiCard('Avg Duration', $avgDuration, '#20c997', 'mdi-timer-outline'),
                $this->kpiCard('Completion Rate', ($total > 0 ? round($completed / $total * 100, 1) : 0) . '%', '#e83e8c', 'mdi-percent'),
            ],
            'charts' => [
                'daily_trend' => $dailyTrend,
                'clinic_dist' => $clinicDist,
                'payer_split' => [
                    ['label' => 'HMO / Insurance', 'value' => $hmoCount],
                    ['label' => 'Private / Self-pay', 'value' => $privateCount],
                ],
            ],
        ]);
    }

    // ─── KPI Strip (lightweight — counts only) ────────────────────────────────

    public function getKpiStrip(Request $request): JsonResponse
    {
        $base = $this->buildBaseQuery($request);
        $total = (clone $base)->count();
        $completed = (clone $base)->where('completed', true)->count();
        $active = $total - $completed;

        $uniquePatients = (clone $base)->distinct('patient_id')->count('patient_id');
        $newPatients = (clone $base)->whereHas('patient', function ($pq) {
            $pq->whereHas('encounters', null, '=', 1);
        })->count();

        $kpis = [
            $this->kpiCard('Total', number_format($total), '#0d6efd', 'mdi-stethoscope'),
            $this->kpiCard('Active', number_format($active), '#17a2b8', 'mdi-account-clock'),
            $this->kpiCard('Completed', number_format($completed), '#198754', 'mdi-check-circle-outline'),
            $this->kpiCard('Patients', number_format($uniquePatients), '#6610f2', 'mdi-account-group'),
            $this->kpiCard('New Today', number_format($newPatients), '#fd7e14', 'mdi-account-plus-outline'),
        ];

        // Finance users also see revenue in the strip
        if ($this->canViewRevenue()) {
            $revenue = DB::table('product_or_service_requests as posr')
                ->join('encounters as e', 'posr.encounter_id', '=', 'e.id')
                ->where('e.created_at', '>=', $this->getStartDate($request))
                ->where('e.created_at', '<=', $this->getEndDate($request))
                ->when(!$this->canViewAnalytics() && $this->userIsDoctor(), function ($q) {
                    $q->where('e.doctor_id', auth()->id());
                })
                ->selectRaw('COALESCE(SUM(posr.amount), 0) as total_billed, COALESCE(SUM(posr.payable_amount), 0) as total_payable')
                ->first();

            $kpis[] = $this->kpiCard(
                'Billed',
                '₦' . number_format($revenue->total_billed ?? 0, 2),
                '#dc3545',
                'mdi-currency-ngn'
            );
        }

        return response()->json(['kpis' => $kpis]);
    }

    // ─── Shared builder ───────────────────────────────────────────────────────

    private function buildBaseQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $q = Encounter::query();
        $this->applyEncounterFilters($q, $request);
        $this->applyRoleScope($q);

        return $q;
    }

    private function getStartDate(Request $request): \Carbon\Carbon
    {
        return $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->subDays(30)->startOfDay();
    }

    private function getEndDate(Request $request): \Carbon\Carbon
    {
        return $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfDay();
    }
}
