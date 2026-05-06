<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\MaternityEnrollment;
use App\Models\AncVisit;
use App\Models\DeliveryRecord;
use App\Models\PostnatalVisit;

class MaternityDashboardService
{
    /**
     * Get maternity stats for dashboard cards
     */
    public function getStats(): array
    {
        return [
            'active' => MaternityEnrollment::where('status', MaternityEnrollment::STATUS_ACTIVE)->count(),
            'anc' => AncVisit::whereDate('visit_date', Carbon::today())->count(),
            'deliveries' => DeliveryRecord::whereDate('delivery_date', Carbon::today())->count(),
            'pnc' => PostnatalVisit::whereDate('visit_date', Carbon::today())->count(),
        ];
    }

    /**
     * Get maternity operation queue counts
     */
    public function getQueueCounts(): array
    {
        return Cache::remember('dashboard.maternity.queues', 30, function () {
            $activeEnrollments = MaternityEnrollment::where('status', MaternityEnrollment::STATUS_ACTIVE)->count();
            $postnatalCare = MaternityEnrollment::where('status', MaternityEnrollment::STATUS_POSTNATAL)->count();
            $ancToday = AncVisit::whereDate('visit_date', Carbon::today())->count();
            $dueSoon = MaternityEnrollment::where('status', MaternityEnrollment::STATUS_ACTIVE)
                ->whereBetween('edd', [now(), now()->addDays(7)])
                ->count();
            $recentDeliveries = DeliveryRecord::whereDate('delivery_date', Carbon::today())->count();

            return [
                ['name' => 'Active ANC', 'filter' => 'active', 'icon' => 'mdi-human-pregnant', 'color' => 'primary',
                 'count' => $activeEnrollments],
                ['name' => 'Postnatal Care', 'filter' => 'postnatal', 'icon' => 'mdi-baby-face-outline', 'color' => 'info',
                 'count' => $postnatalCare],
                ['name' => 'ANC Today', 'filter' => 'anc-today', 'icon' => 'mdi-calendar-clock', 'color' => 'warning',
                 'count' => $ancToday],
                ['name' => 'Deliveries Today', 'filter' => 'deliveries', 'icon' => 'mdi-baby-face-outline', 'color' => 'success',
                 'count' => $recentDeliveries],
            ];
        });
    }

    /**
     * Risk level breakdown for donut chart
     */
    public function getRiskBreakdown(): array
    {
        $lowRisk = MaternityEnrollment::whereIn('status', [MaternityEnrollment::STATUS_ACTIVE, MaternityEnrollment::STATUS_POSTNATAL])->where('risk_level', 'low')->count();
        $medRisk = MaternityEnrollment::whereIn('status', [MaternityEnrollment::STATUS_ACTIVE, MaternityEnrollment::STATUS_POSTNATAL])->where('risk_level', 'medium')->count();
        $highRisk = MaternityEnrollment::whereIn('status', [MaternityEnrollment::STATUS_ACTIVE, MaternityEnrollment::STATUS_POSTNATAL])->where('risk_level', 'high')->count();

        return [
            ['label' => 'Low Risk', 'value' => $lowRisk, 'color' => '#10b981'],
            ['label' => 'Medium Risk', 'value' => $medRisk, 'color' => '#f59e0b'],
            ['label' => 'High Risk', 'value' => $highRisk, 'color' => '#ef4444'],
        ];
    }

    /**
     * Enrollment trend
     */
    public function getEnrollmentTrend(string $start = null, string $end = null): array
    {
        $start = $start ?: Carbon::now()->startOfMonth()->toDateString();
        $end = $end ?: Carbon::today()->toDateString();

        return MaternityEnrollment::whereBetween(DB::raw('DATE(created_at)'), [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Recent maternity activity
     */
    public function getRecentActivity(): array
    {
        $today = Carbon::today();

        // Enrollments
        $enrollments = MaternityEnrollment::with(['patient'])
            ->whereDate('created_at', $today)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
        
        // Deliveries
        $deliveries = DeliveryRecord::with(['enrollment.patient'])
            ->whereDate('delivery_date', $today)
            ->orderByDesc('delivery_date')
            ->limit(5)
            ->get();

        $activity = [];

        foreach ($enrollments as $e) {
            $activity[] = [
                'id' => $e->id,
                'type' => 'Enrollment',
                'ref' => $e->patient?->file_no ?? 'N/A',
                'detail' => $e->patient?->user?->name ?? 'Unknown Patient',
                'status_label' => 'Enrolled',
                'status_color' => 'badge-primary',
                'time' => $e->created_at->format('h:i A'),
                'created_at' => $e->created_at,
            ];
        }

        foreach ($deliveries as $d) {
            $activity[] = [
                'id' => $d->id,
                'type' => 'Delivery',
                'ref' => $d->enrollment?->patient?->file_no ?? 'N/A',
                'detail' => ($d->enrollment?->patient?->user?->name ?? 'Unknown') . ' - ' . ucfirst($d->delivery_mode),
                'status_label' => 'Delivered',
                'status_color' => 'badge-success',
                'time' => $d->created_at->format('h:i A'),
                'created_at' => $d->created_at,
            ];
        }

        usort($activity, function($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });

        return array_slice($activity, 0, 10);
    }

    /**
     * Generate maternity insights
     */
    public function getInsights(): array
    {
        $insights = [];

        // High risk alerts
        $highRiskCount = MaternityEnrollment::whereIn('status', ['active', 'postnatal'])->where('risk_level', 'high')->count();
        if ($highRiskCount > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'danger', 'icon' => 'mdi-alert-decagram',
                'title' => 'High Risk Patients',
                'message' => "{$highRiskCount} high-risk cases (ANC/Postnatal) require close monitoring",
            ];
        }

        // Overdue visits (ANC visits scheduled before today but not completed)
        // Note: Assuming there's a scheduled_date or similar. Let's check AncVisit.
        
        // EDD alerts
        $dueToday = MaternityEnrollment::where('status', 'active')->whereDate('edd', Carbon::today())->count();
        if ($dueToday > 0) {
            $insights[] = [
                'type' => 'info', 'severity' => 'primary', 'icon' => 'mdi-baby-carriage',
                'title' => 'Due Today',
                'message' => "{$dueToday} patient(s) have their EDD today",
            ];
        }

        return $insights;
    }
}
