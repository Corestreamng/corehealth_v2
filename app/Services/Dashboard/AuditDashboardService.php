<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AuditDashboardService
{
    /**
     * Audit summary stats
     */
    public function getSummaryStats(): array
    {
        $today = Carbon::today();
        
        return Cache::remember('dashboard.audit.summary', 60, function () use ($today) {
            $labCategoryId = appsettings('investigation_category_id', 2);
            $imagingCategoryId = appsettings('imaging_category_id', 6);
            
            return [
                'revenue_today' => DB::table('payments')->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->sum('total'),
                'active_admissions' => DB::table('admission_requests')->where('status', 1)->count(), // 1 = admitted
                'pending_requisitions' => DB::table('store_requisitions')->whereIn('status', ['pending', 'partial'])->count(),
                'diagnostic_orders' => DB::table('product_or_service_requests as posr')
                    ->join('services as s', 'posr.service_id', '=', 's.id')
                    ->whereIn('s.category_id', [$labCategoryId, $imagingCategoryId])
                    ->whereBetween('posr.created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                    ->count(),
                'system_discounts_today' => DB::table('payments')->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->sum('total_discount'),
                'refunds_today' => DB::table('credit_notes')->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->sum('amount'),
            ];
        });
    }

    /**
     * Revenue trend for charts
     */
    public function getRevenueTrend(string $start = null, string $end = null): array
    {
        $start = $start ?: Carbon::now()->startOfMonth()->toDateString();
        $end = $end ?: Carbon::today()->toDateString();

        return DB::table('payments')
            ->whereBetween(DB::raw('DATE(created_at)'), [$start, $end])
            ->selectRaw('DATE(created_at) as date, SUM(total) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Recent system audit logs
     */
    public function getRecentAuditLogs(int $limit = 10): array
    {
        // Check if audits table exists (using OwenIt/laravel-auditing or custom)
        if (!DB::getSchemaBuilder()->hasTable('audits')) {
            return [];
        }

        return DB::table('audits')
            ->leftJoin('users', 'audits.user_id', '=', 'users.id')
            ->select(
                'audits.id',
                'audits.event',
                'audits.auditable_type',
                'audits.created_at',
                'users.firstname',
                'users.surname'
            )
            ->orderBy('audits.created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($audit) {
                // Get short class name for module
                $parts = explode('\\', $audit->auditable_type);
                $module = end($parts);
                
                $userName = $audit->firstname ? trim($audit->firstname . ' ' . $audit->surname) : 'System';

                return [
                    'id' => $audit->id,
                    'time' => Carbon::parse($audit->created_at)->diffForHumans(),
                    'full_time' => Carbon::parse($audit->created_at)->format('Y-m-d H:i:s'),
                    'user' => $userName,
                    'event' => ucfirst($audit->event),
                    'module' => $module,
                    'event_color' => $this->getEventColor($audit->event)
                ];
            })
            ->toArray();
    }
    
    private function getEventColor($event)
    {
        switch (strtolower($event)) {
            case 'created': return 'success';
            case 'updated': return 'info';
            case 'deleted': return 'danger';
            case 'restored': return 'warning';
            default: return 'secondary';
        }
    }
}
