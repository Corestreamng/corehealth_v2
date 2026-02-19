<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ReceptionDashboardService
{
    /**
     * Get queue counts mirroring the reception workbench queues
     */
    public function getQueueCounts(): array
    {
        $today = Carbon::today();

        return Cache::remember('dashboard.reception.queues', 30, function () use ($today) {
            return [
                ['name' => 'Waiting', 'filter' => 'waiting', 'icon' => 'mdi-clock-outline', 'color' => 'warning',
                 'count' => DB::table('doctor_queues')->where('status', 1)->whereDate('created_at', $today)->count()],

                ['name' => 'Vitals Pending', 'filter' => 'vitals', 'icon' => 'mdi-heart-pulse', 'color' => 'info',
                 'count' => DB::table('doctor_queues')->where('status', 2)->whereDate('created_at', $today)->count()],

                ['name' => 'In Consultation', 'filter' => 'consultation', 'icon' => 'mdi-doctor', 'color' => 'success',
                 'count' => DB::table('doctor_queues')->where('status', 3)->whereDate('created_at', $today)->count()],

                ['name' => 'Admitted', 'filter' => 'admitted', 'icon' => 'mdi-bed', 'color' => 'danger',
                 'count' => DB::table('admission_requests')->where('discharged', 0)->count()],
            ];
        });
    }

    /**
     * Get recent activity (last 10 queue entries today)
     */
    public function getRecentActivity(): array
    {
        $today = Carbon::today();

        return DB::table('doctor_queues as dq')
            ->join('patients as p', 'dq.patient_id', '=', 'p.id')
            ->join('users as u', 'p.user_id', '=', 'u.id')
            ->leftJoin('clinics as c', 'dq.clinic_id', '=', 'c.id')
            ->whereDate('dq.created_at', $today)
            ->select(
                'dq.id',
                'dq.patient_id',
                DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                'p.file_no',
                'c.name as clinic',
                'dq.status',
                'dq.created_at'
            )
            ->orderByDesc('dq.created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $statusMap = [1 => 'Waiting', 2 => 'Vitals', 3 => 'Consultation', 4 => 'Completed'];
                $colorMap = [1 => 'warning', 2 => 'info', 3 => 'success', 4 => 'secondary'];
                $row->status_label = $statusMap[$row->status] ?? 'Unknown';
                $row->status_color = $colorMap[$row->status] ?? 'secondary';
                $row->time = Carbon::parse($row->created_at)->format('h:i A');
                return $row;
            })
            ->toArray();
    }

    /**
     * Hourly patient flow for today (bar chart data)
     */
    public function getHourlyPatientFlow(): array
    {
        $today = Carbon::today();

        $data = DB::table('doctor_queues')
            ->whereDate('created_at', $today)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as total')
            ->groupByRaw('HOUR(created_at)')
            ->orderBy('hour')
            ->get();

        // Fill in missing hours (7am-8pm)
        $hours = [];
        for ($h = 7; $h <= 20; $h++) {
            $found = $data->firstWhere('hour', $h);
            $hours[] = [
                'label' => Carbon::createFromTime($h)->format('gA'),
                'total' => $found ? $found->total : 0,
            ];
        }

        return $hours;
    }

    /**
     * Patient type breakdown (New vs Returning vs HMO)
     */
    public function getPatientTypeBreakdown(): array
    {
        $today = Carbon::today();

        $newPatients = DB::table('patients')->whereDate('created_at', $today)->count();

        $hmoToday = DB::table('doctor_queues as dq')
            ->join('patients as p', 'dq.patient_id', '=', 'p.id')
            ->whereDate('dq.created_at', $today)
            ->whereNotNull('p.hmo_id')
            ->count();

        $totalToday = DB::table('doctor_queues')->whereDate('created_at', $today)->count();
        $returning = max(0, $totalToday - $newPatients);

        return [
            ['label' => 'New Patients', 'value' => $newPatients, 'color' => '#3b82f6'],
            ['label' => 'Returning', 'value' => $returning, 'color' => '#10b981'],
            ['label' => 'HMO', 'value' => $hmoToday, 'color' => '#8b5cf6'],
        ];
    }

    /**
     * Generate insights for reception
     */
    public function getInsights(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $insights = [];

        $todayCount = DB::table('doctor_queues')->whereDate('created_at', $today)->count();
        $yesterdayCount = DB::table('doctor_queues')->whereDate('created_at', $yesterday)->count();

        if ($yesterdayCount > 0) {
            $change = round((($todayCount - $yesterdayCount) / $yesterdayCount) * 100);
            if ($change > 0) {
                $insights[] = [
                    'type' => 'trend', 'severity' => 'info', 'icon' => 'mdi-trending-up',
                    'title' => 'Patient Volume Up',
                    'message' => "Today's visits are {$change}% higher than yesterday ({$todayCount} vs {$yesterdayCount})",
                ];
            } elseif ($change < -20) {
                $insights[] = [
                    'type' => 'trend', 'severity' => 'warning', 'icon' => 'mdi-trending-down',
                    'title' => 'Lower Volume',
                    'message' => "Today's visits are " . abs($change) . "% lower than yesterday",
                ];
            }
        }

        // Waiting too long
        $longWaiters = DB::table('doctor_queues')
            ->where('status', 1)
            ->whereDate('created_at', $today)
            ->where('created_at', '<', now()->subMinutes(30))
            ->count();

        if ($longWaiters > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'danger', 'icon' => 'mdi-clock-alert',
                'title' => 'Long Wait Times',
                'message' => "{$longWaiters} patient(s) have been waiting over 30 minutes",
            ];
        }

        // New registrations today
        $newToday = DB::table('patients')->whereDate('created_at', $today)->count();
        if ($newToday > 0) {
            $insights[] = [
                'type' => 'stat', 'severity' => 'success', 'icon' => 'mdi-account-plus',
                'title' => 'New Registrations',
                'message' => "{$newToday} new patient(s) registered today",
            ];
        }

        // Admission count
        $admitted = DB::table('admission_requests')->where('discharged', 0)->count();
        if ($admitted > 0) {
            $insights[] = [
                'type' => 'info', 'severity' => 'info', 'icon' => 'mdi-bed',
                'title' => 'Admitted Patients',
                'message' => "{$admitted} patient(s) currently admitted",
            ];
        }

        return $insights;
    }
}
