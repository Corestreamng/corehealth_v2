<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\MorgueAdmission;

class MorgueDashboardService
{
    /**
     * Get morgue stats for dashboard cards
     */
    public function getStats(): array
    {
        return [
            'occupants' => MorgueAdmission::where('status', 'stored')->count(),
            'admissions' => MorgueAdmission::whereDate('arrival_time', Carbon::today())->count(),
            'releases' => MorgueAdmission::whereDate('release_time', Carbon::today())->count(),
            'pending' => MorgueAdmission::where('status', 'stored')
                ->whereNotNull('release_time')
                ->count(),
        ];
    }

    /**
     * Get morgue operation queue counts
     */
    public function getQueueCounts(): array
    {
        return Cache::remember('dashboard.morgue.queues', 30, function () {
            $currentOccupants = MorgueAdmission::where('status', 'stored')->count();
            $admissionsToday = MorgueAdmission::whereDate('arrival_time', Carbon::today())->count();
            $releasesToday = MorgueAdmission::whereDate('release_time', Carbon::today())->count();
            $pendingRelease = MorgueAdmission::where('status', 'stored')
                ->whereNotNull('release_time')
                ->whereDate('release_time', Carbon::today())
                ->count();

            return [
                ['name' => 'Current Occupants', 'filter' => 'admitted', 'icon' => 'mdi-emoticon-dead', 'color' => 'dark',
                 'count' => $currentOccupants],
                ['name' => 'Admissions Today', 'filter' => 'admissions', 'icon' => 'mdi-login-variant', 'color' => 'primary',
                 'count' => $admissionsToday],
                ['name' => 'Releases Today', 'filter' => 'released', 'icon' => 'mdi-logout-variant', 'color' => 'success',
                 'count' => $releasesToday],
                ['name' => 'Pending Release', 'filter' => 'pending-release', 'icon' => 'mdi-clock-outline', 'color' => 'warning',
                 'count' => $pendingRelease],
            ];
        });
    }

    /**
     * Status breakdown
     */
    public function getStatusBreakdown(): array
    {
        $stored = MorgueAdmission::where('status', 'stored')->count();
        $released = MorgueAdmission::where('status', 'released')->count();

        return [
            ['label' => 'Stored', 'value' => $stored, 'color' => '#64748b'],
            ['label' => 'Released', 'value' => $released, 'color' => '#10b981'],
        ];
    }

    /**
     * Admission trend
     */
    public function getAdmissionTrend(string $start = null, string $end = null): array
    {
        $start = $start ?: Carbon::now()->startOfMonth()->toDateString();
        $end = $end ?: Carbon::today()->toDateString();

        return MorgueAdmission::whereBetween(DB::raw('DATE(arrival_time)'), [$start, $end])
            ->selectRaw('DATE(arrival_time) as date, COUNT(*) as total')
            ->groupByRaw('DATE(arrival_time)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Recent morgue activity
     */
    public function getRecentActivity(): array
    {
        $today = Carbon::today();

        $admissions = MorgueAdmission::with(['patient', 'deathRecord'])
            ->whereDate('arrival_time', $today)
            ->orWhereDate('release_time', $today)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $activity = [];

        foreach ($admissions as $a) {
            $isRelease = $a->status === 'released' && $a->release_time?->isToday();
            $activity[] = [
                'id' => $a->id,
                'type' => $isRelease ? 'Release' : 'Admission',
                'ref' => $a->body_code ?? $a->patient?->file_no ?? 'N/A',
                'detail' => ($a->patient?->user?->name ?? 'Unknown') . ($a->fridge_number ? " (Fridge {$a->fridge_number})" : ""),
                'status_label' => ucfirst($a->status),
                'status_color' => $a->status === 'stored' ? 'badge-dark' : 'badge-success',
                'time' => ($isRelease ? $a->release_time : $a->arrival_time)?->format('h:i A') ?? 'N/A',
                'created_at' => $isRelease ? $a->release_time : $a->arrival_time,
            ];
        }

        return $activity;
    }

    /**
     * Generate morgue insights
     */
    public function getInsights(): array
    {
        $insights = [];

        // Long stay alert (> 30 days)
        $longStayCount = MorgueAdmission::where('status', 'stored')
            ->where('arrival_time', '<', now()->subDays(30))
            ->count();
        
        if ($longStayCount > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'warning', 'icon' => 'mdi-clock-alert',
                'title' => 'Long Stay Bodies',
                'message' => "{$longStayCount} bodies have been in the morgue for over 30 days",
            ];
        }

        return $insights;
    }
}
