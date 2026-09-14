<?php

namespace App\Http\Controllers\EncounterWorkbench\Tabs;

use App\Http\Controllers\EncounterWorkbench\EncounterWorkbenchBaseController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tab 6 — Patient Insights
 * Return rates, admission rates, new vs returning, high-frequency patients,
 * diagnosis frequency, referral outcomes.
 * Admin / Accounts / Doctors (own patients).
 */
class PatientInsightsTab extends EncounterWorkbenchBaseController
{
    public function getData(Request $request): JsonResponse
    {
        if (!$this->canViewAnalytics() && !$this->userIsDoctor()) {
            abort(403, 'Access denied.');
        }

        $start = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->subDays(30)->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        $section = $request->input('section', 'overview');

        return match ($section) {
            'return_rate' => $this->returnRateData($start, $end, $request),
            'high_frequency' => $this->highFrequencyPatients($start, $end, $request),
            'referral_outcomes' => $this->referralOutcomes($start, $end, $request),
            default => $this->overviewData($start, $end, $request),
        };
    }

    // ─── Overview ─────────────────────────────────────────────────────────────

    private function overviewData(Carbon $start, Carbon $end, Request $request): JsonResponse
    {
        $baseEnc = $this->buildEncBase($start, $end, $request);

        $totalEnc = (clone $baseEnc)->count();

        $uniquePatientsQuery = clone $baseEnc;
        $uniquePatients = $uniquePatientsQuery->distinct('patient_id')->count('patient_id');

        // New patients (first encounter ever, not just in period)
        $newPatients = (clone $baseEnc)->whereHas('patient', function ($pq) {
            $pq->whereHas('encounters', null, '=', 1);
        })->count();

        $returningPatients = $uniquePatients - $newPatients;

        // Admissions in period
        $admissions = DB::table('admission_requests as ar')
            ->join('encounters as e', 'ar.encounter_id', '=', 'e.id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->when(!$this->canViewAnalytics() && $this->userIsDoctor(), fn ($q) => $q->where('e.doctor_id', auth()->id()))
            ->count();

        $admissionRate = $totalEnc > 0 ? round($admissions / $totalEnc * 100, 1) : 0;

        // Return within 30 days (patient had > 1 enc in period)
        $returnIn30 = DB::table('encounters as e')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->when(!$this->canViewAnalytics() && $this->userIsDoctor(), fn ($q) => $q->where('e.doctor_id', auth()->id()))
            ->selectRaw('e.patient_id, COUNT(*) as cnt')
            ->groupBy('e.patient_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        // Average encounters per patient
        $avgEncPerPatient = $uniquePatients > 0 ? round($totalEnc / $uniquePatients, 2) : 0;

        // New vs returning trend (by week)
        $weeklyTrend = DB::table('encounters as e')
            ->leftJoin(DB::raw('(SELECT patient_id, MIN(id) as first_enc_id FROM encounters GROUP BY patient_id) fe'), 'fe.patient_id', '=', 'e.patient_id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->when(!$this->canViewAnalytics() && $this->userIsDoctor(), fn ($q) => $q->where('e.doctor_id', auth()->id()))
            ->selectRaw('
                YEARWEEK(e.created_at, 1) as week_num,
                MIN(DATE(e.created_at)) as week_start,
                COUNT(*) as total,
                SUM(CASE WHEN e.id = fe.first_enc_id THEN 1 ELSE 0 END) as new_patients,
                SUM(CASE WHEN e.id != fe.first_enc_id THEN 1 ELSE 0 END) as returning_patients
            ')
            ->groupBy('week_num')
            ->orderBy('week_num')
            ->get()
            ->map(fn ($r) => [
                'week' => Carbon::parse($r->week_start)->format('d M'),
                'total' => (int) $r->total,
                'new' => (int) $r->new_patients,
                'returning' => (int) $r->returning_patients,
            ]);

        return response()->json([
            'kpis' => [
                $this->kpiCard('Unique Patients', number_format($uniquePatients), '#0d6efd', 'mdi-account-group'),
                $this->kpiCard('New Patients', number_format($newPatients), '#198754', 'mdi-account-plus-outline'),
                $this->kpiCard('Returning Patients', number_format($returningPatients), '#17a2b8', 'mdi-account-reactivate'),
                $this->kpiCard('Admission Rate', $admissionRate . '%', '#dc3545', 'mdi-bed'),
                $this->kpiCard('Return Rate (30d)', ($uniquePatients > 0 ? round($returnIn30 / $uniquePatients * 100, 1) : 0) . '%', '#fd7e14', 'mdi-refresh'),
                $this->kpiCard('Avg Enc / Patient', $avgEncPerPatient, '#6610f2', 'mdi-repeat'),
            ],
            'charts' => [
                'weekly_trend' => $weeklyTrend,
                'new_vs_returning' => [
                    ['label' => 'New', 'value' => $newPatients],
                    ['label' => 'Returning', 'value' => $returningPatients],
                ],
            ],
        ]);
    }

    // ─── Return Rate Breakdown ────────────────────────────────────────────────

    private function returnRateData(Carbon $start, Carbon $end, Request $request): JsonResponse
    {
        $returnWindows = [30, 60, 90];
        $results = [];

        foreach ($returnWindows as $days) {
            $count = DB::table('encounters as e1')
                ->join('encounters as e2', function ($j) use ($days) {
                    $j->on('e2.patient_id', '=', 'e1.patient_id')
                        ->whereColumn('e2.id', '!=', 'e1.id')
                        ->whereRaw("e2.created_at > e1.created_at")
                        ->whereRaw("DATEDIFF(e2.created_at, e1.created_at) <= {$days}");
                })
                ->whereBetween('e1.created_at', [$start, $end])
                ->whereNull('e1.deleted_at')
                ->when(!$this->canViewAnalytics() && $this->userIsDoctor(), fn ($q) => $q->where('e1.doctor_id', auth()->id()))
                ->distinct('e1.patient_id')
                ->count('e1.patient_id');

            $results[] = ['window' => "Within {$days} days", 'patients' => $count];
        }

        return response()->json(['data' => $results]);
    }

    // ─── High-Frequency Patients ──────────────────────────────────────────────

    private function highFrequencyPatients(Carbon $start, Carbon $end, Request $request): JsonResponse
    {
        $minEnc = max(2, (int) $request->input('min_encounters', 3));

        $rows = DB::table('encounters as e')
            ->join('patients as p', 'e.patient_id', '=', 'p.id')
            ->join('users as u', 'p.user_id', '=', 'u.id')
            ->leftJoin('hmos as h', 'p.hmo_id', '=', 'h.id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->when(!$this->canViewAnalytics() && $this->userIsDoctor(), fn ($q) => $q->where('e.doctor_id', auth()->id()))
            ->selectRaw('
                p.id as patient_id,
                p.file_no,
                CONCAT(COALESCE(u.surname,""), " ", COALESCE(u.firstname,""), " ", COALESCE(u.othername,"")) as patient_name,
                COALESCE(h.name, "Private") as hmo,
                COUNT(*) as enc_count,
                MIN(DATE(e.created_at)) as first_visit,
                MAX(DATE(e.created_at)) as last_visit
            ')
            ->groupBy('p.id', 'p.file_no', 'u.surname', 'u.firstname', 'u.othername', 'h.name')
            ->havingRaw('COUNT(*) >= ?', [$minEnc])
            ->orderByDesc('enc_count')
            ->limit(100)
            ->get()
            ->map(fn ($r) => [
                'patient' => '<strong>' . e(trim($r->patient_name)) . '</strong><br><small class="text-muted">#' . e($r->file_no) . '</small>',
                'hmo' => e($r->hmo),
                'enc_count' => '<span class="badge bg-primary">' . $r->enc_count . '</span>',
                'first_visit' => $r->first_visit ? Carbon::parse($r->first_visit)->format('d M Y') : '—',
                'last_visit' => $r->last_visit ? Carbon::parse($r->last_visit)->format('d M Y') : '—',
            ])
            ->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $rows->count(),
            'recordsFiltered' => $rows->count(),
            'data' => $rows,
        ]);
    }

    // ─── Referral Outcomes ────────────────────────────────────────────────────

    private function referralOutcomes(Carbon $start, Carbon $end, Request $request): JsonResponse
    {
        $rows = DB::table('specialist_referrals as sr')
            ->join('encounters as e', 'sr.encounter_id', '=', 'e.id')
            ->leftJoin('users as doc', 'sr.referring_doctor_id', '=', 'doc.id')
            ->leftJoin('clinics as tc', 'sr.target_clinic_id', '=', 'tc.id')
            ->whereBetween('e.created_at', [$start, $end])
            ->whereNull('e.deleted_at')
            ->when(!$this->canViewAnalytics() && $this->userIsDoctor(), fn ($q) => $q->where('e.doctor_id', auth()->id()))
            ->selectRaw('
                sr.status,
                sr.referral_type,
                COUNT(*) as total
            ')
            ->groupBy('sr.status', 'sr.referral_type')
            ->orderByDesc('total')
            ->get();

        $statusColors = [
            'pending' => 'warning',
            'booked' => 'info',
            'completed' => 'success',
            'referred_out' => 'primary',
            'declined' => 'danger',
            'cancelled' => 'secondary',
        ];

        $tableRows = $rows->map(fn ($r) => [
            'status' => '<span class="badge bg-' . ($statusColors[$r->status] ?? 'secondary') . '">' . ucfirst(str_replace('_', ' ', $r->status)) . '</span>',
            'type' => '<span class="badge bg-' . ($r->referral_type === 'external' ? 'danger' : 'primary') . '">' . ucfirst($r->referral_type) . '</span>',
            'total' => number_format($r->total),
        ])->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $tableRows->count(),
            'recordsFiltered' => $tableRows->count(),
            'data' => $tableRows,
            'chart_data' => [
                'labels' => $rows->map(fn ($r) => ucfirst(str_replace('_', ' ', $r->status)))->toArray(),
                'values' => $rows->pluck('total')->toArray(),
            ],
        ]);
    }

    // ─── Builder ──────────────────────────────────────────────────────────────

    private function buildEncBase(Carbon $start, Carbon $end, Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $q = \App\Models\Encounter::query()
            ->whereBetween('created_at', [$start, $end]);

        if ($request->filled('clinic_id')) {
            $q->whereHas('queue', fn ($qr) => $qr->where('clinic_id', $request->clinic_id));
        }

        if ($request->filled('doctor_id')) {
            $q->where('doctor_id', $request->doctor_id);
        }

        $this->applyRoleScope($q);

        return $q;
    }
}
