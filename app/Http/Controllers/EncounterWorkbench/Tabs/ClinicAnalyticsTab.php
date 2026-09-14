<?php

namespace App\Http\Controllers\EncounterWorkbench\Tabs;

use App\Http\Controllers\EncounterWorkbench\EncounterWorkbenchBaseController;
use App\Models\Encounter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tab 3 — Clinic Analytics
 * Aggregated per-clinic breakdown. Admin / Accounts only.
 */
class ClinicAnalyticsTab extends EncounterWorkbenchBaseController
{
    public function getData(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        if (!$this->canViewAnalytics()) {
            abort(403, 'Access denied: admin or accounts role required.');
        }

        $start = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->subDays(30)->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        // Base encounter query with date + optional clinic filter
        $encBase = Encounter::query()
            ->whereBetween('encounters.created_at', [$start, $end]);

        if ($request->filled('clinic_id')) {
            $encBase->whereHas('queue', fn ($q) => $q->where('clinic_id', $request->clinic_id));
        }

        if ($request->filled('doctor_id')) {
            $encBase->where('doctor_id', $request->doctor_id);
        }

        if ($request->filled('hmo_id')) {
            $encBase->whereHas('patient', fn ($q) => $q->where('hmo_id', $request->hmo_id));
        }

        // Aggregate per clinic via raw GROUP BY (far faster than PHP-side iteration)
        $rows = DB::table('encounters as e')
            ->join('doctor_queues as dq', 'e.queue_id', '=', 'dq.id')
            ->join('clinics as cl', 'dq.clinic_id', '=', 'cl.id')
            ->leftJoin('lab_service_requests as lsr', 'lsr.encounter_id', '=', 'e.id')
            ->leftJoin('imaging_service_requests as isr', 'isr.encounter_id', '=', 'e.id')
            ->leftJoin('product_requests as pr', 'pr.encounter_id', '=', 'e.id')
            ->leftJoin('specialist_referrals as sref', 'sref.encounter_id', '=', 'e.id')
            ->leftJoin('admission_requests as ar', 'ar.encounter_id', '=', 'e.id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->when($request->filled('clinic_id'), fn ($q) => $q->where('dq.clinic_id', $request->clinic_id))
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('e.doctor_id', $request->doctor_id))
            ->when($request->filled('hmo_id'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('patients')
                    ->whereColumn('patients.id', 'e.patient_id')
                    ->where('patients.hmo_id', $request->hmo_id);
            }))
            ->selectRaw('
                cl.id as clinic_id,
                cl.name as clinic_name,
                COUNT(DISTINCT e.id) as total_encounters,
                COUNT(DISTINCT e.patient_id) as unique_patients,
                SUM(CASE WHEN e.completed = 1 THEN 1 ELSE 0 END) as completed,
                AVG(CASE WHEN e.started_at IS NOT NULL AND e.completed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, e.started_at, e.completed_at)
                    ELSE NULL END) as avg_duration_min,
                COUNT(DISTINCT lsr.id) as lab_requests,
                COUNT(DISTINCT isr.id) as imaging_requests,
                COUNT(DISTINCT pr.id) as prescriptions,
                COUNT(DISTINCT sref.id) as referrals,
                COUNT(DISTINCT ar.id) as admissions
            ')
            ->groupBy('cl.id', 'cl.name')
            ->orderByDesc('total_encounters')
            ->get();

        // Return rates: patients with > 1 encounter per clinic in period
        $returnCounts = DB::table('encounters as e')
            ->join('doctor_queues as dq', 'e.queue_id', '=', 'dq.id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->selectRaw('dq.clinic_id, e.patient_id, COUNT(*) as enc_count')
            ->groupBy('dq.clinic_id', 'e.patient_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->groupBy('clinic_id')
            ->map(fn ($g) => $g->count());

        $tableData = $rows->map(function ($row) use ($returnCounts) {
            $admissionRate = $row->total_encounters > 0
                ? round($row->admissions / $row->total_encounters * 100, 1)
                : 0;

            $completionRate = $row->total_encounters > 0
                ? round($row->completed / $row->total_encounters * 100, 1)
                : 0;

            $returnPatients = $returnCounts->get($row->clinic_id, 0);

            return [
                'clinic' => '<strong>' . e($row->clinic_name) . '</strong>',
                'total_encounters' => number_format($row->total_encounters),
                'unique_patients' => number_format($row->unique_patients),
                'return_patients' => number_format($returnPatients),
                'completed' => '<span class="badge bg-success">' . number_format($row->completed) . '</span>',
                'completion_rate' => '<span class="text-' . ($completionRate >= 70 ? 'success' : 'warning') . ' font-weight-bold">' . $completionRate . '%</span>',
                'avg_duration' => $row->avg_duration_min !== null ? round($row->avg_duration_min) . ' min' : '—',
                'lab_requests' => number_format($row->lab_requests),
                'imaging_requests' => number_format($row->imaging_requests),
                'prescriptions' => number_format($row->prescriptions),
                'referrals' => number_format($row->referrals),
                'admissions' => number_format($row->admissions),
                'admission_rate' => '<span class="text-' . ($admissionRate > 10 ? 'danger' : 'info') . '">' . $admissionRate . '%</span>',
                'clinic_id' => $row->clinic_id,
            ];
        })->values();

        // Summary KPIs
        $totalEnc = $rows->sum('total_encounters');
        $totalComplete = $rows->sum('completed');
        $totalAdm = $rows->sum('admissions');

        $kpis = [
            $this->kpiCard('Total Clinics', number_format($rows->count()), '#0d6efd', 'mdi-hospital-building'),
            $this->kpiCard('Total Encounters', number_format($totalEnc), '#17a2b8', 'mdi-stethoscope'),
            $this->kpiCard('Total Admissions', number_format($totalAdm), '#dc3545', 'mdi-bed'),
            $this->kpiCard('Overall Completion', ($totalEnc > 0 ? round($totalComplete / $totalEnc * 100, 1) : 0) . '%', '#198754', 'mdi-check-circle'),
            $this->kpiCard('Busiest Clinic', $rows->first()?->clinic_name ?? '—', '#fd7e14', 'mdi-crown'),
        ];

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $tableData->count(),
            'recordsFiltered' => $tableData->count(),
            'data' => $tableData,
            'kpis' => $kpis,
            'chart_data' => [
                'labels' => $rows->pluck('clinic_name')->toArray(),
                'total' => $rows->pluck('total_encounters')->toArray(),
                'admissions' => $rows->pluck('admissions')->toArray(),
            ],
        ]);
    }
}
