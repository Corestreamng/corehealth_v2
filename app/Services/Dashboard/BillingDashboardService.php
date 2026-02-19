<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class BillingDashboardService
{
    /**
     * Get billing queue counts mirroring billing workbench
     */
    public function getQueueCounts(): array
    {
        $today = Carbon::today();

        return Cache::remember('dashboard.billing.queues', 30, function () use ($today) {
            $baseQuery = DB::table('product_or_service_requests as posr')
                ->join('patients as p', 'posr.user_id', '=', 'p.id')
                ->whereNull('posr.payment_id')
                ->whereDate('posr.created_at', $today);

            $allUnpaid = (clone $baseQuery)->count();

            $hmoItems = (clone $baseQuery)->whereNotNull('p.hmo_id')->count();

            $creditAccounts = DB::table('patient_accounts')
                ->where('balance', '<', 0)
                ->count();

            return [
                ['name' => 'All Unpaid', 'filter' => 'all', 'icon' => 'mdi-cash-clock', 'color' => 'warning',
                 'count' => $allUnpaid],
                ['name' => 'HMO Items', 'filter' => 'hmo', 'icon' => 'mdi-shield-check', 'color' => 'success',
                 'count' => $hmoItems],
                ['name' => 'Credit Accounts', 'filter' => 'credit', 'icon' => 'mdi-account-alert', 'color' => 'danger',
                 'count' => $creditAccounts],
            ];
        });
    }

    /**
     * Payment method breakdown for pie chart
     */
    public function getPaymentMethodBreakdown(): array
    {
        $today = Carbon::today();

        $methods = DB::table('payments')
            ->whereDate('created_at', $today)
            ->selectRaw("COALESCE(payment_type, 'Cash') as method, COUNT(*) as total, SUM(total) as amount")
            ->groupBy('payment_type')
            ->get();

        $colors = ['Cash' => '#10b981', 'POS' => '#3b82f6', 'Transfer' => '#8b5cf6', 'HMO' => '#f59e0b', 'Credit' => '#ef4444'];

        return $methods->map(function ($m) use ($colors) {
            return [
                'label' => $m->method ?: 'Cash',
                'value' => (float)$m->amount,
                'count' => (int)$m->total,
                'color' => $colors[$m->method] ?? '#6b7280',
            ];
        })->toArray();
    }

    /**
     * Recent payments
     */
    public function getRecentActivity(): array
    {
        $today = Carbon::today();

        return DB::table('payments as pay')
            ->leftJoin('users as u', 'pay.user_id', '=', 'u.id')
            ->leftJoin('patients as pt', 'pay.patient_id', '=', 'pt.id')
            ->leftJoin('users as pu', 'pt.user_id', '=', 'pu.id')
            ->whereDate('pay.created_at', $today)
            ->select(
                'pay.id',
                'pay.patient_id',
                'pay.total as amount',
                'pay.payment_type',
                'pay.created_at',
                DB::raw("CONCAT(u.surname, ' ', u.firstname) as processed_by"),
                DB::raw("CONCAT(pu.surname, ' ', pu.firstname) as patient_name")
            )
            ->orderByDesc('pay.created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->amount_formatted = '₦' . number_format($row->amount, 2);
                $row->time = Carbon::parse($row->created_at)->format('h:i A');
                $row->method = $row->payment_type ?: 'Cash';
                return $row;
            })
            ->toArray();
    }

    /**
     * Revenue trend (real data for this month)
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
     * Generate insights for billing
     */
    public function getInsights(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $insights = [];

        $todayRevenue = DB::table('payments')->whereDate('created_at', $today)->sum('total');
        $yesterdayRevenue = DB::table('payments')->whereDate('created_at', $yesterday)->sum('total');

        if ($todayRevenue > 0) {
            $insights[] = [
                'type' => 'stat', 'severity' => 'success', 'icon' => 'mdi-cash-check',
                'title' => "Today's Revenue",
                'message' => '₦' . number_format($todayRevenue, 2) . ' collected so far',
            ];
        }

        if ($yesterdayRevenue > 0) {
            $change = round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100);
            $direction = $change >= 0 ? 'up' : 'down';
            $insights[] = [
                'type' => 'trend', 'severity' => $change >= 0 ? 'success' : 'warning',
                'icon' => $change >= 0 ? 'mdi-trending-up' : 'mdi-trending-down',
                'title' => 'Revenue Trend',
                'message' => "Revenue is {$direction} " . abs($change) . '% vs yesterday',
            ];
        }

        // Outstanding amount
        $outstanding = DB::table('product_or_service_requests')
            ->whereNull('payment_id')
            ->sum('payable_amount');

        if ($outstanding > 0) {
            $outstandingCount = DB::table('product_or_service_requests')
                ->whereNull('payment_id')
                ->distinct('user_id')
                ->count('user_id');

            $insights[] = [
                'type' => 'alert', 'severity' => 'danger', 'icon' => 'mdi-alert-circle',
                'title' => 'Outstanding Balance',
                'message' => '₦' . number_format($outstanding, 2) . " across {$outstandingCount} patient(s)",
            ];
        }

        // Average transaction value
        $avgTransaction = DB::table('payments')->whereDate('created_at', $today)->avg('total');
        if ($avgTransaction) {
            $insights[] = [
                'type' => 'info', 'severity' => 'info', 'icon' => 'mdi-calculator',
                'title' => 'Avg Transaction',
                'message' => '₦' . number_format($avgTransaction, 2) . ' per payment today',
            ];
        }

        return $insights;
    }
}
