<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveBalance;
use App\Models\User;

class EssDashboardService
{
    /**
     * Get ESS stats for dashboard cards
     */
    public function getStats(int $userId): array
    {
        $staff = \App\Models\Staff::where('user_id', $userId)->first();
        $staffId = $staff?->id ?? 0;

        return [
            'balance' => LeaveBalance::where('staff_id', $staffId)->sum(DB::raw('entitled_days - used_days')),
            'pending' => LeaveRequest::where('staff_id', $staffId)->where('status', 'pending')->count(),
            'upcoming' => LeaveRequest::where('staff_id', $staffId)->where('status', 'approved')
                ->where('start_date', '>=', now())
                ->count(),
            'payslip' => 'Ready',
        ];
    }

    /**
     * Get personal dashboard queue counts
     */
    public function getQueueCounts(int $userId): array
    {
        $staff = \App\Models\Staff::where('user_id', $userId)->first();
        $staffId = $staff?->id ?? 0;

        return Cache::remember("dashboard.ess.queues.{$userId}", 30, function () use ($staffId) {
            $pendingLeave = LeaveRequest::where('staff_id', $staffId)->where('status', 'pending')->count();
            $approvedLeave = LeaveRequest::where('staff_id', $staffId)->where('status', 'approved')
                ->where('start_date', '>=', now())
                ->count();
            
            $totalBalance = LeaveBalance::where('staff_id', $staffId)->sum(DB::raw('entitled_days - used_days'));

            return [
                ['name' => 'Pending Requests', 'filter' => 'pending', 'icon' => 'mdi-clock-outline', 'color' => 'warning',
                 'count' => $pendingLeave],
                ['name' => 'Upcoming Leave', 'filter' => 'upcoming', 'icon' => 'mdi-calendar-check', 'color' => 'success',
                 'count' => $approvedLeave],
                ['name' => 'Leave Balance', 'filter' => 'balance', 'icon' => 'mdi-wallet-membership', 'color' => 'primary',
                 'count' => $totalBalance],
            ];
        });
    }

    /**
     * Leave breakdown by type
     */
    public function getLeaveBreakdown(int $userId): array
    {
        $staff = \App\Models\Staff::where('user_id', $userId)->first();
        $staffId = $staff?->id ?? 0;

        return LeaveBalance::with('leaveType')
            ->where('staff_id', $staffId)
            ->get()
            ->map(function ($b) {
                return [
                    'label' => $b->leaveType->name ?? 'Unknown',
                    'value' => (int)($b->entitled_days - $b->used_days),
                ];
            })
            ->toArray();
    }

    /**
     * Recent personal activity
     */
    public function getRecentActivity(int $userId): array
    {
        $staff = \App\Models\Staff::where('user_id', $userId)->first();
        $staffId = $staff?->id ?? 0;

        $requests = LeaveRequest::with('leaveType')
            ->where('staff_id', $staffId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $activity = [];

        foreach ($requests as $r) {
            $activity[] = [
                'id' => $r->id,
                'type' => 'Leave Request',
                'ref' => $r->leaveType?->name ?? 'Leave',
                'detail' => $r->start_date->format('M d') . ' - ' . $r->end_date->format('M d'),
                'status_label' => ucfirst($r->status),
                'status_color' => match($r->status) {
                    'approved' => 'badge-success',
                    'rejected' => 'badge-danger',
                    'pending' => 'badge-warning',
                    default => 'badge-secondary'
                },
                'time' => $r->created_at->format('M d, H:i'),
                'created_at' => $r->created_at,
            ];
        }

        return $activity;
    }

    /**
     * Generate personal insights
     */
    public function getInsights(int $userId): array
    {
        $insights = [];
        $staff = \App\Models\Staff::where('user_id', $userId)->first();
        $staffId = $staff?->id ?? 0;

        // Check if on leave
        $onLeave = LeaveRequest::where('staff_id', $staffId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->first();
        
        if ($onLeave) {
            $insights[] = [
                'type' => 'info', 'severity' => 'success', 'icon' => 'mdi-palm-tree',
                'title' => 'You are on Leave',
                'message' => "Enjoy your break! You are expected back on " . $onLeave->end_date->addDay()->format('M d, Y'),
            ];
        }

        // Low balance alert
        $lowBalance = LeaveBalance::where('staff_id', $staffId)->sum(DB::raw('entitled_days - used_days'));
        if ($lowBalance < 5) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'warning', 'icon' => 'mdi-alert-circle-outline',
                'title' => 'Low Leave Balance',
                'message' => "You have less than 5 days of leave remaining for this cycle",
            ];
        }

        return $insights;
    }
}
