<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AccountsDashboardService
{
    /**
     * Financial summary stats
     */
    public function getFinancialSummary(): array
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $yearStart = Carbon::now()->startOfYear();

        return Cache::remember('dashboard.accounts.summary', 60, function () use ($today, $monthStart, $yearStart) {
            return [
                'revenue_today' => DB::table('payments')->whereDate('created_at', $today)->sum('total'),
                'revenue_month' => DB::table('payments')->where('created_at', '>=', $monthStart)->sum('total'),
                'revenue_year' => DB::table('payments')->where('created_at', '>=', $yearStart)->sum('total'),
                'outstanding' => DB::table('product_or_service_requests')->whereNull('payment_id')->sum('payable_amount'),
                'deposits_held' => DB::table('patient_accounts')->where('balance', '>', 0)->sum('balance'),
                'hmo_receivables' => DB::table('product_or_service_requests as posr')
                    ->join('patients as p', 'posr.user_id', '=', 'p.id')
                    ->whereNotNull('p.hmo_id')
                    ->where('posr.validation_status', 'approved')
                    ->whereNull('posr.payment_id')
                    ->sum('posr.claims_amount'),
                'payments_today' => DB::table('payments')->whereDate('created_at', $today)->count(),
                'patients_billed_today' => DB::table('product_or_service_requests')->whereDate('created_at', $today)->distinct('user_id')->count('user_id'),
            ];
        });
    }

    /**
     * Revenue vs date trend
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
     * Payment method breakdown for donut chart
     */
    public function getPaymentMethodBreakdown(): array
    {
        $colors = ['Cash' => '#10b981', 'POS' => '#3b82f6', 'Transfer' => '#8b5cf6', 'HMO' => '#f59e0b', 'Credit' => '#ef4444'];

        return DB::table('payments')
            ->whereMonth('created_at', now()->month)
            ->selectRaw("COALESCE(payment_type, 'Cash') as label, SUM(total) as value")
            ->groupBy('payment_type')
            ->get()
            ->map(function ($row) use ($colors) {
                return [
                    'label' => $row->label ?: 'Cash',
                    'value' => (float)$row->value,
                    'color' => $colors[$row->label] ?? '#6b7280',
                ];
            })
            ->toArray();
    }

    /**
     * Department revenue split for horizontal bar chart
     */
    public function getDepartmentRevenue(): array
    {
        return DB::table('product_or_service_requests as posr')
            ->join('payments as pay', 'posr.payment_id', '=', 'pay.id')
            ->leftJoin('services as s', 'posr.service_id', '=', 's.id')
            ->leftJoin('service_categories as sc', 's.category_id', '=', 'sc.id')
            ->whereMonth('pay.created_at', now()->month)
            ->selectRaw("COALESCE(sc.category_name, 'Products') as department, SUM(posr.payable_amount) as total")
            ->groupBy(DB::raw("COALESCE(sc.category_name, 'Products')"))
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->toArray();
    }

    /**
     * Outstanding aging analysis
     */
    public function getOutstandingAging(): array
    {
        $now = Carbon::now();

        $aging = [
            '0-30 days' => DB::table('product_or_service_requests')
                ->whereNull('payment_id')
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->sum('payable_amount'),
            '30-60 days' => DB::table('product_or_service_requests')
                ->whereNull('payment_id')
                ->whereBetween('created_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])
                ->sum('payable_amount'),
            '60-90 days' => DB::table('product_or_service_requests')
                ->whereNull('payment_id')
                ->whereBetween('created_at', [$now->copy()->subDays(90), $now->copy()->subDays(60)])
                ->sum('payable_amount'),
            '90+ days' => DB::table('product_or_service_requests')
                ->whereNull('payment_id')
                ->where('created_at', '<', $now->copy()->subDays(90))
                ->sum('payable_amount'),
        ];

        return $aging;
    }

    /**
     * Recent audit log entries
     */
    public function getAuditLog(int $limit = 20, array $filters = []): array
    {
        $query = DB::table('audits as a')
            ->leftJoin('users as u', 'a.user_id', '=', 'u.id')
            ->select(
                'a.id',
                DB::raw("CONCAT(u.surname, ' ', u.firstname) as user_name"),
                'a.event',
                'a.auditable_type',
                'a.auditable_id',
                'a.old_values',
                'a.new_values',
                'a.created_at'
            )
            ->orderByDesc('a.created_at');

        if (!empty($filters['user_id'])) {
            $query->where('a.user_id', $filters['user_id']);
        }

        if (!empty($filters['event'])) {
            $query->where('a.event', $filters['event']);
        }

        if (!empty($filters['module'])) {
            $query->where('a.auditable_type', 'like', '%' . $filters['module'] . '%');
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('a.created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('a.created_at', '<=', $filters['date_to']);
        }

        return $query->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->time = Carbon::parse($row->created_at)->format('M d, h:i A');
                $row->module = class_basename($row->auditable_type ?? '');
                $eventColors = ['created' => 'success', 'updated' => 'info', 'deleted' => 'danger'];
                $row->event_color = $eventColors[$row->event] ?? 'secondary';
                return $row;
            })
            ->toArray();
    }

    /**
     * Financial KPIs
     */
    public function getFinancialKpis(): array
    {
        $monthStart = Carbon::now()->startOfMonth();

        // Average Revenue Per Patient
        $totalRevMonth = DB::table('payments')->where('created_at', '>=', $monthStart)->sum('total');
        $uniquePatients = DB::table('product_or_service_requests')
            ->whereNotNull('payment_id')
            ->where('created_at', '>=', $monthStart)
            ->distinct('user_id')
            ->count('user_id');
        $avgRevPerPatient = $uniquePatients > 0 ? round($totalRevMonth / $uniquePatients, 2) : 0;

        // Collection Rate
        $totalBilled = DB::table('product_or_service_requests')
            ->where('created_at', '>=', $monthStart)
            ->sum('payable_amount');
        $totalCollected = $totalRevMonth;
        $collectionRate = $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100, 1) : 0;

        return [
            'avg_revenue_per_patient' => $avgRevPerPatient,
            'collection_rate' => $collectionRate,
            'total_billed_month' => $totalBilled,
            'total_collected_month' => $totalCollected,
            'outstanding_aging' => $this->getOutstandingAging(),
        ];
    }

    /**
     * Generate insights for accounts
     */
    public function getInsights(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $insights = [];

        $todayRevenue = DB::table('payments')->whereDate('created_at', $today)->sum('total');
        $yesterdayRevenue = DB::table('payments')->whereDate('created_at', $yesterday)->sum('total');

        if ($yesterdayRevenue > 0) {
            $change = round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100);
            $insights[] = [
                'type' => 'trend',
                'severity' => $change >= 0 ? 'success' : 'warning',
                'icon' => $change >= 0 ? 'mdi-trending-up' : 'mdi-trending-down',
                'title' => 'Revenue Trend',
                'message' => 'Revenue is ' . ($change >= 0 ? 'up' : 'down') . ' ' . abs($change) . "% vs yesterday (₦" . number_format($todayRevenue, 2) . ')',
            ];
        }

        // Outstanding
        $outstanding = DB::table('product_or_service_requests')
            ->whereNull('payment_id')
            ->sum('payable_amount');

        if ($outstanding > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'danger', 'icon' => 'mdi-cash-clock',
                'title' => 'Total Outstanding',
                'message' => '₦' . number_format($outstanding, 2) . ' in unpaid bills',
            ];
        }

        // Collection rate
        $monthStart = Carbon::now()->startOfMonth();
        $billed = DB::table('product_or_service_requests')->where('created_at', '>=', $monthStart)->sum('payable_amount');
        $collected = DB::table('payments')->where('created_at', '>=', $monthStart)->sum('total');
        if ($billed > 0) {
            $rate = round(($collected / $billed) * 100, 1);
            $insights[] = [
                'type' => 'stat', 'severity' => $rate >= 80 ? 'success' : 'warning',
                'icon' => 'mdi-percent',
                'title' => 'Collection Rate',
                'message' => "{$rate}% collected this month (₦" . number_format($collected, 2) . ' of ₦' . number_format($billed, 2) . ')',
            ];
        }

        return $insights;
    }
}
