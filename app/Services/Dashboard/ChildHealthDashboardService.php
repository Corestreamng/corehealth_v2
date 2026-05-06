<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\ImmunizationRecord;
use App\Models\PatientImmunizationSchedule;
use App\Models\ChildGrowthRecord;

class ChildHealthDashboardService
{
    /**
     * Get child health stats for dashboard cards
     */
    public function getStats(): array
    {
        $today = Carbon::today();
        return [
            'today' => PatientImmunizationSchedule::whereIn('status', ['pending', 'due'])
                ->whereDate('scheduled_date', $today)
                ->count(),
            'overdue' => PatientImmunizationSchedule::where('status', 'overdue')
                ->count(),
            'growth' => ChildGrowthRecord::whereDate('created_at', $today)->count(),
            'new' => \App\Models\Patient::where('dob', '>=', now()->subYears(18))
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];
    }

    /**
     * Get child health operation queue counts
     */
    public function getQueueCounts(): array
    {
        return Cache::remember('dashboard.child_health.queues', 30, function () {
            $today = Carbon::today();
            $immunizationsDue = PatientImmunizationSchedule::whereIn('status', ['pending', 'due'])
                ->whereDate('due_date', $today)
                ->count();
            
            $growthVisits = ChildGrowthRecord::whereDate('created_at', $today)->count();
            
            $overdueImmunizations = PatientImmunizationSchedule::where('status', 'overdue')
                ->count();

            return [
                ['name' => 'Immunizations Today', 'filter' => 'due-today', 'icon' => 'mdi-needle', 'color' => 'primary',
                 'count' => $immunizationsDue],
                ['name' => 'Growth Monitoring', 'filter' => 'growth', 'icon' => 'mdi-chart-line', 'color' => 'success',
                 'count' => $growthVisits],
                ['name' => 'Overdue Shots', 'filter' => 'overdue', 'icon' => 'mdi-alert-circle-outline', 'color' => 'danger',
                 'count' => $overdueImmunizations],
            ];
        });
    }

    /**
     * Immunization breakdown
     */
    public function getImmunizationBreakdown(): array
    {
        return PatientImmunizationSchedule::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get()
            ->map(function ($s) {
                return [
                    'label' => ucfirst($s->status),
                    'value' => (int)$s->total,
                    'color' => match($s->status) {
                        'administered' => '#10b981',
                        'pending', 'due' => '#f59e0b',
                        'overdue' => '#ef4444',
                        default => '#64748b'
                    }
                ];
            })
            ->toArray();
    }

    /**
     * Recent activity
     */
    public function getRecentActivity(): array
    {
        $today = Carbon::today();

        $schedules = PatientImmunizationSchedule::with(['patient.user', 'scheduleItem'])
            ->whereDate('due_date', $today)
            ->orderByDesc('due_date')
            ->limit(10)
            ->get();

        $activity = [];

        foreach ($schedules as $s) {
            $activity[] = [
                'id' => $s->id,
                'type' => 'Immunization',
                'ref' => $s->patient?->user?->name ?? 'Patient',
                'detail' => $s->scheduleItem?->name ?? 'Vaccine',
                'status_label' => ucfirst($s->status),
                'status_color' => match($s->status) {
                    'administered' => 'badge-success',
                    'overdue' => 'badge-danger',
                    default => 'badge-warning'
                },
                'time' => $s->due_date?->format('M d') ?? $s->created_at->format('M d'),
                'created_at' => $s->created_at,
            ];
        }

        return $activity;
    }

    /**
     * Generate insights
     */
    public function getInsights(): array
    {
        $insights = [];

        $overdue = PatientImmunizationSchedule::where('status', 'overdue')
            ->count();
        
        if ($overdue > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'danger', 'icon' => 'mdi-needle',
                'title' => 'Critical Overdue Shots',
                'message' => "{$overdue} immunization(s) are overdue by more than 7 days",
            ];
        }

        return $insights;
    }
}
