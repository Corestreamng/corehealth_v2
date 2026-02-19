<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class NursingDashboardService
{
    /**
     * Get nursing queue counts mirroring nursing workbench
     */
    public function getQueueCounts(): array
    {
        $today = Carbon::today();

        return Cache::remember('dashboard.nursing.queues', 30, function () use ($today) {
            $admitted = DB::table('admission_requests')->where('discharged', 0)->count();

            $vitalsQueue = DB::table('vital_signs')
                ->whereDate('created_at', $today)
                ->where('status', 0)
                ->count();

            $bedRequests = DB::table('admission_requests')
                ->where('status', 0)
                ->count();

            $dischargeRequests = DB::table('admission_requests')
                ->where('status', 2) // discharge requested
                ->where('discharged', 0)
                ->count();

            $medicationDue = DB::table('medication_schedules as ms')
                ->leftJoin('medication_administrations as ma', 'ms.id', '=', 'ma.schedule_id')
                ->whereDate('ms.scheduled_time', $today)
                ->whereNull('ma.id')
                ->count();

            return [
                ['name' => 'Admitted Patients', 'filter' => 'admitted', 'icon' => 'mdi-bed', 'color' => 'danger',
                 'count' => $admitted],
                ['name' => 'Vitals Queue', 'filter' => 'vitals', 'icon' => 'mdi-heart-pulse', 'color' => 'warning',
                 'count' => $vitalsQueue],
                ['name' => 'Bed Requests', 'filter' => 'bed-requests', 'icon' => 'mdi-bed-empty', 'color' => 'info',
                 'count' => $bedRequests],
                ['name' => 'Discharge Requests', 'filter' => 'discharge-requests', 'icon' => 'mdi-account-minus', 'color' => 'success',
                 'count' => $dischargeRequests],
                ['name' => 'Medication Due', 'filter' => 'medication-due', 'icon' => 'mdi-pill', 'color' => 'purple',
                 'count' => $medicationDue],
                ['name' => 'Emergency Queue', 'filter' => 'emergency', 'icon' => 'mdi-ambulance', 'color' => 'danger',
                 'count' => DB::table('admission_requests')->where('priority', 'emergency')->where('discharged', 0)->count()],
            ];
        });
    }

    /**
     * Real bed occupancy data for donut chart
     */
    public function getBedOccupancy(): array
    {
        $totalBeds = DB::table('beds')->count();
        $occupied = DB::table('beds')->where('status', 'occupied')->count();
        $reserved = DB::table('beds')->where('status', 'reserved')->count();
        $available = max(0, $totalBeds - $occupied - $reserved);

        return [
            ['label' => 'Occupied', 'value' => $occupied, 'color' => '#ef4444'],
            ['label' => 'Available', 'value' => $available, 'color' => '#10b981'],
            ['label' => 'Reserved', 'value' => $reserved, 'color' => '#f59e0b'],
        ];
    }

    /**
     * Vitals trend (real data for this month)
     */
    public function getVitalsTrend(string $start = null, string $end = null): array
    {
        $start = $start ?: Carbon::now()->startOfMonth()->toDateString();
        $end = $end ?: Carbon::today()->toDateString();

        return DB::table('vital_signs')
            ->where('status', 1)
            ->whereBetween(DB::raw('DATE(created_at)'), [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Recent nursing activities
     */
    public function getRecentActivity(): array
    {
        $today = Carbon::today();

        // Recent vitals taken
        $vitals = DB::table('vital_signs as vs')
            ->join('patients as p', 'vs.patient_id', '=', 'p.id')
            ->join('users as u', 'p.user_id', '=', 'u.id')
            ->whereDate('vs.created_at', $today)
            ->where('vs.status', 1)
            ->select(
                'vs.id',
                'vs.patient_id',
                DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                DB::raw("'Vitals Taken' as activity"),
                DB::raw("'success' as activity_color"),
                'vs.created_at'
            )
            ->orderByDesc('vs.created_at')
            ->limit(10);

        // Recent medication administrations
        $meds = DB::table('medication_administrations as ma')
            ->join('medication_schedules as ms', 'ma.schedule_id', '=', 'ms.id')
            ->join('patients as p', 'ms.patient_id', '=', 'p.id')
            ->join('users as u', 'p.user_id', '=', 'u.id')
            ->whereDate('ma.created_at', $today)
            ->select(
                'ma.id',
                'ms.patient_id',
                DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                DB::raw("'Medication Given' as activity"),
                DB::raw("'info' as activity_color"),
                'ma.created_at'
            )
            ->orderByDesc('ma.created_at')
            ->limit(10);

        return $vitals->union($meds)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->time = Carbon::parse($row->created_at)->format('h:i A');
                return $row;
            })
            ->toArray();
    }

    /**
     * Generate insights for nursing
     */
    public function getInsights(): array
    {
        $today = Carbon::today();
        $insights = [];

        // Bed occupancy rate
        $totalBeds = DB::table('beds')->count();
        $occupied = DB::table('beds')->where('status', 'occupied')->count();
        if ($totalBeds > 0) {
            $rate = round(($occupied / $totalBeds) * 100);
            $severity = $rate >= 90 ? 'danger' : ($rate >= 70 ? 'warning' : 'success');
            $insights[] = [
                'type' => 'stat', 'severity' => $severity, 'icon' => 'mdi-bed',
                'title' => 'Bed Occupancy',
                'message' => "{$rate}% occupied ({$occupied}/{$totalBeds} beds)",
            ];
        }

        // Overdue medications
        $overdue = DB::table('medication_schedules as ms')
            ->leftJoin('medication_administrations as ma', 'ms.id', '=', 'ma.schedule_id')
            ->whereDate('ms.scheduled_time', $today)
            ->where('ms.scheduled_time', '<', now())
            ->whereNull('ma.id')
            ->count();

        if ($overdue > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'danger', 'icon' => 'mdi-clock-alert',
                'title' => 'Overdue Medications',
                'message' => "{$overdue} medication(s) past scheduled time",
            ];
        }

        // Pending discharge requests
        $pendingDischarges = DB::table('admission_requests')
            ->where('status', 2)
            ->where('discharged', 0)
            ->count();

        if ($pendingDischarges > 0) {
            $insights[] = [
                'type' => 'info', 'severity' => 'info', 'icon' => 'mdi-account-minus',
                'title' => 'Pending Discharges',
                'message' => "{$pendingDischarges} discharge request(s) awaiting processing",
            ];
        }

        // Vitals recorded today
        $vitalsToday = DB::table('vital_signs')
            ->whereDate('created_at', $today)
            ->where('status', 1)
            ->count();

        $insights[] = [
            'type' => 'stat', 'severity' => 'success', 'icon' => 'mdi-heart-pulse',
            'title' => 'Vitals Today',
            'message' => "{$vitalsToday} vital sign(s) recorded",
        ];

        return $insights;
    }
}
