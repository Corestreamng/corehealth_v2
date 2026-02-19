<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DoctorDashboardService
{
    /**
     * Get doctor queue/consultation state counts
     */
    public function getQueueCounts(int $userId): array
    {
        $today = Carbon::today();
        $staffId = DB::table('staff')->where('user_id', $userId)->value('id') ?? 0;

        return Cache::remember("dashboard.doctor.queues.{$userId}", 30, function () use ($today, $userId, $staffId) {
            $myQueue = DB::table('doctor_queues')
                ->whereDate('created_at', $today)
                ->where('staff_id', $staffId)
                ->where('status', 1) // waiting
                ->count();

            $inProgress = DB::table('encounters')
                ->whereDate('created_at', $today)
                ->where('doctor_id', $userId)
                ->whereNull('notes')
                ->count();

            $completedToday = DB::table('encounters')
                ->whereDate('created_at', $today)
                ->where('doctor_id', $userId)
                ->whereNotNull('notes')
                ->count();

            $pendingResults = DB::table('lab_service_requests as lsr')
                ->join('encounters as e', 'lsr.encounter_id', '=', 'e.id')
                ->where('e.doctor_id', $userId)
                ->whereIn('lsr.status', [2, 3])
                ->count();

            return [
                ['name' => 'My Queue', 'filter' => 'queue', 'icon' => 'mdi-clock-outline', 'color' => 'warning',
                 'count' => $myQueue],
                ['name' => 'In Progress', 'filter' => 'in-progress', 'icon' => 'mdi-stethoscope', 'color' => 'info',
                 'count' => $inProgress],
                ['name' => 'Completed Today', 'filter' => 'completed', 'icon' => 'mdi-check-circle', 'color' => 'success',
                 'count' => $completedToday],
                ['name' => 'Pending Results', 'filter' => 'results', 'icon' => 'mdi-flask', 'color' => 'danger',
                 'count' => $pendingResults],
            ];
        });
    }

    /**
     * Recent consultations
     */
    public function getRecentActivity(int $userId): array
    {
        $today = Carbon::today();

        return DB::table('encounters as e')
            ->join('patients as p', 'e.patient_id', '=', 'p.id')
            ->join('users as u', 'p.user_id', '=', 'u.id')
            ->leftJoin('doctor_queues as dq', function ($join) {
                $join->on('dq.patient_id', '=', 'e.patient_id')
                     ->on('dq.request_entry_id', '=', 'e.service_request_id');
            })
            ->leftJoin('clinics as c', 'dq.clinic_id', '=', 'c.id')
            ->where('e.doctor_id', $userId)
            ->whereDate('e.created_at', $today)
            ->select(
                'e.id',
                'e.patient_id',
                DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                'c.name as clinic',
                DB::raw("CASE WHEN e.notes IS NOT NULL THEN 'Completed' ELSE 'In Progress' END as status_label"),
                DB::raw("CASE WHEN e.notes IS NOT NULL THEN 'success' ELSE 'warning' END as status_color"),
                'e.created_at'
            )
            ->orderByDesc('e.created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->time = Carbon::parse($row->created_at)->format('h:i A');
                return $row;
            })
            ->toArray();
    }

    /**
     * Generate insights for doctor
     */
    public function getInsights(int $userId): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $insights = [];

        $todayCount = DB::table('encounters')
            ->where('doctor_id', $userId)
            ->whereDate('created_at', $today)
            ->count();

        $yesterdayCount = DB::table('encounters')
            ->where('doctor_id', $userId)
            ->whereDate('created_at', $yesterday)
            ->count();

        // Avg comparison
        $avgDaily = DB::table('encounters')
            ->where('doctor_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('COUNT(*) / 30.0 as avg_daily')
            ->value('avg_daily');

        $insights[] = [
            'type' => 'stat', 'severity' => 'info', 'icon' => 'mdi-clipboard-text',
            'title' => 'Consultations Today',
            'message' => "{$todayCount} patient(s) seen (avg: " . round($avgDaily ?? 0, 1) . '/day)',
        ];

        // Pending lab results for my patients
        $pendingResults = DB::table('lab_service_requests as lsr')
            ->join('encounters as e', 'lsr.encounter_id', '=', 'e.id')
            ->where('e.doctor_id', $userId)
            ->where('lsr.status', 3)
            ->count();

        if ($pendingResults > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'warning', 'icon' => 'mdi-flask',
                'title' => 'Lab Results Ready',
                'message' => "{$pendingResults} result(s) awaiting your review",
            ];
        }

        // Ward rounds
        $wardRounds = DB::table('admission_requests as ar')
            ->join('encounters as e', 'ar.encounter_id', '=', 'e.id')
            ->where('e.doctor_id', $userId)
            ->where('ar.discharged', 0)
            ->count();

        if ($wardRounds > 0) {
            $insights[] = [
                'type' => 'info', 'severity' => 'info', 'icon' => 'mdi-bed',
                'title' => 'Ward Rounds',
                'message' => "{$wardRounds} admitted patient(s) under your care",
            ];
        }

        return $insights;
    }
}
