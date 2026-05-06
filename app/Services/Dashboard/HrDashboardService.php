<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\HR\LeaveRequest;
use App\Models\Staff;
use App\Models\User;

class HrDashboardService
{
    /**
     * Get HR stats for dashboard cards
     */
    public function getStats(): array
    {
        return [
            'total' => Staff::where('status', 1)->count(),
            'leave' => LeaveRequest::where('status', 'approved')
                ->whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now())
                ->count(),
            'pending' => LeaveRequest::where('status', 'pending')->count(),
            'new' => Staff::whereMonth('date_hired', now()->month)
                ->whereYear('date_hired', now()->year)
                ->count(),
        ];
    }

    /**
     * Get HR operation queue counts
     */
    public function getQueueCounts(): array
    {
        return Cache::remember('dashboard.hr.queues', 30, function () {
            $pendingLeave = LeaveRequest::where('status', 'pending')->count();
            $onLeaveToday = LeaveRequest::where('status', 'approved')
                ->whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now())
                ->count();
            
            $totalStaff = Staff::where('status', 1)->count();
            
            $newHiresThisMonth = Staff::whereMonth('date_hired', now()->month)
                ->whereYear('date_hired', now()->year)
                ->count();

            return [
                ['name' => 'Pending Leave', 'filter' => 'pending-leave', 'icon' => 'mdi-calendar-clock', 'color' => 'warning',
                 'count' => $pendingLeave],
                ['name' => 'Staff on Leave', 'filter' => 'on-leave', 'icon' => 'mdi-palm-tree', 'color' => 'info',
                 'count' => $onLeaveToday],
                ['name' => 'Total Staff', 'filter' => 'all-staff', 'icon' => 'mdi-account-group', 'color' => 'primary',
                 'count' => $totalStaff],
                ['name' => 'New Hires', 'filter' => 'new-hires', 'icon' => 'mdi-account-plus', 'color' => 'success',
                 'count' => $newHiresThisMonth],
            ];
        });
    }

    /**
     * Department breakdown
     */
    public function getDepartmentBreakdown(): array
    {
        return DB::table('staff')
            ->join('departments', 'staff.department_id', '=', 'departments.id')
            ->selectRaw('departments.name as label, count(*) as value')
            ->where('staff.status', 1)
            ->groupBy('departments.name')
            ->get()
            ->toArray();
    }

    /**
     * Recent HR activity
     */
    public function getRecentActivity(): array
    {
        $requests = LeaveRequest::with(['staff.user', 'leaveType'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $activity = [];

        foreach ($requests as $r) {
            $activity[] = [
                'id' => $r->id,
                'type' => 'Leave Request',
                'ref' => $r->staff?->user?->name ?? 'Staff',
                'detail' => ($r->leaveType?->name ?? 'Leave') . ": " . $r->start_date->format('M d') . ' - ' . $r->end_date->format('M d'),
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
     * Generate HR insights
     */
    public function getInsights(): array
    {
        $insights = [];

        // Pending leave alert
        $pendingCount = LeaveRequest::where('status', 'pending')->count();
        if ($pendingCount > 5) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'warning', 'icon' => 'mdi-alert-circle-outline',
                'title' => 'Leave Backlog',
                'message' => "There are {$pendingCount} leave requests awaiting approval",
            ];
        }

        // Staff on leave today
        $onLeave = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->count();
        if ($onLeave > 0) {
            $insights[] = [
                'type' => 'info', 'severity' => 'info', 'icon' => 'mdi-information-outline',
                'title' => 'Staff Availability',
                'message' => "{$onLeave} staff member(s) are currently on leave",
            ];
        }

        return $insights;
    }
}
