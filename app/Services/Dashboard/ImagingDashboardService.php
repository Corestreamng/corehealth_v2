<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ImagingDashboardService
{
    /**
     * Get imaging queue counts mirroring workbench
     */
    public function getQueueCounts(): array
    {
        $today = Carbon::today();
        
        return Cache::remember('dashboard.imaging.queues', 30, function () use ($today) {
            // Imaging queues
            $billing = DB::table('imaging_service_requests')
                ->where('status', 0)
                ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->count();

            $ongoing = DB::table('imaging_service_requests')
                ->whereIn('status', [1, 2])
                ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->count();

            $results = DB::table('imaging_service_requests')
                ->where('status', 3)
                ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->count();

            $completedToday = DB::table('imaging_service_requests')
                ->where('status', 4)
                ->whereBetween('updated_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->count();

            return [
                ['name' => 'Awaiting Billing', 'filter' => 'billing', 'icon' => 'mdi-cash-clock', 'color' => 'warning',
                 'count' => $billing],
                ['name' => 'Ongoing Scans', 'filter' => 'ongoing', 'icon' => 'mdi-radiobox-marked', 'color' => 'info',
                 'count' => $ongoing],
                ['name' => 'Result Entry', 'filter' => 'results', 'icon' => 'mdi-clipboard-edit', 'color' => 'danger',
                 'count' => $results],
                ['name' => 'Completed Today', 'filter' => 'completed', 'icon' => 'mdi-check-circle', 'color' => 'success',
                 'count' => $completedToday],
            ];
        });
    }

    /**
     * Get imaging stats for dashboard cards
     */
    public function getStats(): array
    {
        $today = Carbon::today();
        return [
            'pending' => DB::table('imaging_service_requests')->whereIn('status', [0, 1, 2, 3])->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count(),
            'completed' => DB::table('imaging_service_requests')->where('status', 4)->whereBetween('updated_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count(),
            'scans_this_month' => DB::table('imaging_service_requests')
                ->where('status', 4)
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->count(),
            'services' => DB::table('services')->where('category_id', appsettings('imaging_category_id', 6))->count(),
        ];
    }

    /**
     * Service category breakdown for donut chart
     */
    public function getCategoryBreakdown(): array
    {
        return DB::table('imaging_service_requests as isr')
            ->join('services as s', 'isr.service_id', '=', 's.id')
            ->selectRaw('s.service_name as label, COUNT(*) as value')
            ->groupBy('s.service_name')
            ->orderByDesc('value')
            ->limit(5)
            ->get()
            ->map(function ($row, $index) {
                $colors = ['#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#ec4899'];
                $row->color = $colors[$index % count($colors)];
                return (array) $row;
            })
            ->toArray();
    }

    /**
     * Imaging request volume trend
     */
    public function getRequestTrend(): array
    {
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::today()->toDateString();

        return DB::table('imaging_service_requests')
            ->whereBetween(DB::raw('DATE(created_at)'), [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Recent activity
     */
    public function getRecentActivity(): array
    {
        return DB::table('imaging_service_requests as isr')
            ->join('services as s', 'isr.service_id', '=', 's.id')
            ->join('patients as p', 'isr.patient_id', '=', 'p.id')
            ->join('users as u', 'p.user_id', '=', 'u.id')
            ->select(
                'isr.id',
                DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                's.service_name as test_name',
                'isr.status',
                'isr.created_at'
            )
            ->orderByDesc('isr.created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $statusMap = [0 => 'Billing', 1 => 'Billed', 2 => 'Ongoing', 3 => 'Results', 4 => 'Completed'];
                $colorMap = [0 => 'warning', 1 => 'info', 2 => 'primary', 3 => 'danger', 4 => 'success'];
                $row->status_label = $statusMap[$row->status] ?? 'Unknown';
                $row->status_color = $colorMap[$row->status] ?? 'secondary';
                $row->time = Carbon::parse($row->created_at)->format('h:i A');
                return $row;
            })
            ->toArray();
    }

    /**
     * Insights for imaging
     */
    public function getInsights(): array
    {
        $today = Carbon::today();
        $insights = [];

        $pending = DB::table('imaging_service_requests')->where('status', 3)->count();
        if ($pending > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'warning', 'icon' => 'mdi-clipboard-alert',
                'title' => 'Pending Results',
                'message' => "{$pending} scan(s) awaiting result entry",
            ];
        }

        $completedToday = DB::table('imaging_service_requests')->where('status', 4)->whereBetween('updated_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->count();
        $insights[] = [
            'type' => 'stat', 'severity' => 'success', 'icon' => 'mdi-check-circle',
            'title' => 'Completed Today',
            'message' => "{$completedToday} scan(s) finalized today",
        ];

        return $insights;
    }
}
