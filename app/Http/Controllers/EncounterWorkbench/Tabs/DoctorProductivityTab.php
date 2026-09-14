<?php

namespace App\Http\Controllers\EncounterWorkbench\Tabs;

use App\Http\Controllers\EncounterWorkbench\EncounterWorkbenchBaseController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Tab 4 — Doctor Productivity
 * Aggregated per-doctor breakdown.
 * Admin / Accounts see all doctors.
 * Doctors see ONLY their own row.
 */
class DoctorProductivityTab extends EncounterWorkbenchBaseController
{
    public function getData(Request $request): JsonResponse
    {
        // Doctors are allowed but scoped; non-clinical non-admins are denied
        if (!$this->canViewAnalytics() && !$this->userIsDoctor()) {
            abort(403, 'Access denied.');
        }

        $start = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->subDays(30)->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        $query = DB::table('encounters as e')
            ->join('users as u', 'e.doctor_id', '=', 'u.id')
            ->leftJoin('doctor_queues as dq', 'e.queue_id', '=', 'dq.id')
            ->leftJoin('clinics as cl', 'dq.clinic_id', '=', 'cl.id')
            ->leftJoin('lab_service_requests as lsr', 'lsr.encounter_id', '=', 'e.id')
            ->leftJoin('imaging_service_requests as isr', 'isr.encounter_id', '=', 'e.id')
            ->leftJoin('product_requests as pr', 'pr.encounter_id', '=', 'e.id')
            ->leftJoin('specialist_referrals as sref', 'sref.encounter_id', '=', 'e.id')
            ->leftJoin('procedures as proc', 'proc.encounter_id', '=', 'e.id')
            ->leftJoin('admission_requests as ar', 'ar.encounter_id', '=', 'e.id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            // Doctor self-scope
            ->when(!$this->canViewAnalytics() && $this->userIsDoctor(), fn ($q) => $q->where('e.doctor_id', Auth::id()))
            // Optional filters
            ->when($request->filled('clinic_id'), fn ($q) => $q->where('dq.clinic_id', $request->clinic_id))
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('e.doctor_id', $request->doctor_id))
            ->when($request->filled('hmo_id'), fn ($q) => $q->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('patients')
                    ->whereColumn('patients.id', 'e.patient_id')
                    ->where('patients.hmo_id', $request->hmo_id);
            }))
            ->selectRaw('
                u.id as doctor_id,
                CONCAT(COALESCE(u.surname,""), " ", COALESCE(u.firstname,""), " ", COALESCE(u.othername,"")) as doctor_name,
                MIN(cl.name) as primary_clinic,
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
                COUNT(DISTINCT proc.id) as procedures,
                COUNT(DISTINCT ar.id) as admissions
            ')
            ->groupBy('u.id', 'u.surname', 'u.firstname', 'u.othername')
            ->orderByDesc('total_encounters');

        $rows = $query->get();

        $tableData = $rows->map(function ($row) {
            $completionRate = $row->total_encounters > 0
                ? round($row->completed / $row->total_encounters * 100, 1)
                : 0;

            $initial = mb_strtoupper(mb_substr(trim($row->doctor_name), 0, 1));
            $avatarHtml = '<div class="ewb-doctor-avatar">' . $initial . '</div>';

            return [
                'doctor' => $avatarHtml . '<div class="d-inline-block align-middle"><strong>' . e(trim($row->doctor_name)) . '</strong></div>',
                'clinic' => e($row->primary_clinic ?? '—'),
                'total' => number_format($row->total_encounters),
                'unique_patients' => number_format($row->unique_patients),
                'avg_duration' => $row->avg_duration_min !== null ? round($row->avg_duration_min) . ' min' : '—',
                'completion_rate' => '<span class="font-weight-bold text-' . ($completionRate >= 70 ? 'success' : 'warning') . '">' . $completionRate . '%</span>',
                'lab_requests' => number_format($row->lab_requests),
                'imaging' => number_format($row->imaging_requests),
                'prescriptions' => number_format($row->prescriptions),
                'referrals' => number_format($row->referrals),
                'procedures' => number_format($row->procedures),
                'admissions' => number_format($row->admissions),
                'drill_btn' => '<button class="btn btn-xs btn-outline-primary ewb-drill-doctor" data-doctor-id="' . $row->doctor_id . '" data-doctor-name="' . e(trim($row->doctor_name)) . '"><i class="mdi mdi-eye"></i> View</button>',
                'doctor_id' => $row->doctor_id,
            ];
        })->values();

        $totalEnc = $rows->sum('total_encounters');
        $topDoctor = $rows->first();

        $kpis = [
            $this->kpiCard('Total Doctors', number_format($rows->count()), '#0d6efd', 'mdi-doctor'),
            $this->kpiCard('Total Encounters', number_format($totalEnc), '#17a2b8', 'mdi-stethoscope'),
            $this->kpiCard('Top Doctor', $topDoctor ? trim($topDoctor->doctor_name) : '—', '#fd7e14', 'mdi-crown'),
            $this->kpiCard('Most Active Clinic', $topDoctor?->primary_clinic ?? '—', '#6610f2', 'mdi-hospital-building'),
        ];

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $tableData->count(),
            'recordsFiltered' => $tableData->count(),
            'data' => $tableData,
            'kpis' => $kpis,
            'chart_data' => [
                'labels' => $rows->map(fn ($r) => trim($r->doctor_name))->toArray(),
                'total' => $rows->pluck('total_encounters')->toArray(),
                'completed' => $rows->pluck('completed')->toArray(),
            ],
        ]);
    }
}
