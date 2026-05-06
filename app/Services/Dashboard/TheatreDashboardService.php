<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\Procedure;

class TheatreDashboardService
{
    /**
     * Get theatre stats for dashboard cards
     */
    public function getStats(): array
    {
        $today = Carbon::today();
        return [
            'scheduled' => Procedure::whereDate('scheduled_date', $today)->count(),
            'ongoing' => Procedure::where('procedure_status', Procedure::STATUS_IN_PROGRESS)->count(),
            'completed' => Procedure::where('procedure_status', Procedure::STATUS_COMPLETED)->whereDate('actual_end_time', $today)->count(),
            'pending' => Procedure::where('procedure_status', Procedure::STATUS_SCHEDULED)->where('scheduled_date', '<=', now())->count(),
        ];
    }

    /**
     * Get theatre operation queue counts
     */
    public function getQueueCounts(): array
    {
        return Cache::remember('dashboard.theatre.queues', 30, function () {
            $today = Carbon::today();
            $scheduledToday = Procedure::whereDate('scheduled_date', $today)->count();
            $pending = Procedure::where('procedure_status', Procedure::STATUS_SCHEDULED)->where('scheduled_date', '<=', now())->count();
            $ongoing = Procedure::where('procedure_status', Procedure::STATUS_IN_PROGRESS)->count();
            $completedToday = Procedure::where('procedure_status', Procedure::STATUS_COMPLETED)->whereDate('actual_end_time', $today)->count();

            return [
                ['name' => 'Scheduled Today', 'filter' => 'today', 'icon' => 'mdi-calendar-clock', 'color' => 'primary',
                 'count' => $scheduledToday],
                ['name' => 'Pending Start', 'filter' => 'pending', 'icon' => 'mdi-clock-outline', 'color' => 'warning',
                 'count' => $pending],
                ['name' => 'Ongoing', 'filter' => 'ongoing', 'icon' => 'mdi-pulse', 'color' => 'danger',
                 'count' => $ongoing],
                ['name' => 'Completed Today', 'filter' => 'completed', 'icon' => 'mdi-check-circle-outline', 'color' => 'success',
                 'count' => $completedToday],
            ];
        });
    }

    /**
     * Procedure category breakdown
     */
    public function getCategoryBreakdown(): array
    {
        return DB::table('procedures')
            ->join('procedure_definitions', 'procedures.procedure_definition_id', '=', 'procedure_definitions.id')
            ->join('procedure_categories', 'procedure_definitions.procedure_category_id', '=', 'procedure_categories.id')
            ->selectRaw('procedure_categories.name as label, count(*) as value')
            ->groupBy('procedure_categories.name')
            ->get()
            ->toArray();
    }

    /**
     * Recent activity
     */
    public function getRecentActivity(): array
    {
        $today = Carbon::today();

        $procedures = Procedure::with(['patient.user', 'procedureDefinition'])
            ->whereDate('scheduled_date', $today)
            ->orWhereDate('actual_end_time', $today)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $activity = [];

        foreach ($procedures as $p) {
            $activity[] = [
                'id' => $p->id,
                'type' => 'Surgery',
                'ref' => $p->patient?->file_no ?? 'N/A',
                'detail' => ($p->patient?->user?->name ?? 'Unknown') . ' - ' . ($p->procedureDefinition?->name ?? 'Procedure'),
                'status_label' => ucfirst($p->procedure_status),
                'status_color' => match($p->procedure_status) {
                    Procedure::STATUS_COMPLETED => 'badge-success',
                    Procedure::STATUS_IN_PROGRESS => 'badge-danger',
                    Procedure::STATUS_SCHEDULED => 'badge-primary',
                    default => 'badge-secondary'
                },
                'time' => ($p->actual_start_time ?? $p->scheduled_date)?->format('h:i A') ?? 'N/A',
                'created_at' => $p->updated_at,
            ];
        }

        return $activity;
    }

    /**
     * Generate theatre insights
     */
    public function getInsights(): array
    {
        $insights = [];

        $ongoingCount = Procedure::where('procedure_status', Procedure::STATUS_IN_PROGRESS)->count();
        if ($ongoingCount > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'danger', 'icon' => 'mdi-pulse',
                'title' => 'Theatres Active',
                'message' => "{$ongoingCount} procedure(s) currently ongoing in the theatre",
            ];
        }

        $delayedCount = Procedure::where('procedure_status', Procedure::STATUS_SCHEDULED)
            ->where('scheduled_date', '<', now()->subHours(2))
            ->count();
        if ($delayedCount > 0) {
            $insights[] = [
                'type' => 'info', 'severity' => 'warning', 'icon' => 'mdi-clock-alert-outline',
                'title' => 'Schedule Delay',
                'message' => "{$delayedCount} procedure(s) are delayed by more than 2 hours",
            ];
        }

        return $insights;
    }
}
