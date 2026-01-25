<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\Staff;
use App\Models\Department;
use App\Models\UserCategory;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * HRMS Implementation Plan - Section 7.2
 * Leave Calendar Controller - Global HR Calendar View
 * 
 * Features:
 * - Full calendar view of all approved/pending leaves
 * - Color-coded by leave type or status
 * - Filter by department, leave type, staff, status
 * - Export calendar data
 * - Leave statistics dashboard
 * - Conflict detection (multiple people off same day)
 */
class LeaveCalendarController extends Controller
{
    /**
     * Display the HR leave calendar dashboard
     */
    public function index(Request $request)
    {
        $leaveTypes = LeaveType::active()->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        $userCategories = UserCategory::orderBy('name')->get();
        
        // Quick stats
        $stats = $this->getCalendarStats();
        
        return view('admin.hr.leave-calendar.index', compact(
            'leaveTypes',
            'departments',
            'userCategories',
            'stats'
        ));
    }

    /**
     * Get calendar events for FullCalendar
     */
    public function events(Request $request)
    {
        $start = $request->input('start');
        $end = $request->input('end');
        
        $query = LeaveRequest::with(['staff.user', 'staff.department', 'staff.specialization', 'leaveType'])
            ->whereIn('status', [
                LeaveRequest::STATUS_APPROVED,
                LeaveRequest::STATUS_SUPERVISOR_APPROVED,
                LeaveRequest::STATUS_PENDING,
            ]);
        
        // Date range filter
        if ($start && $end) {
            $query->where(function($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                  ->orWhereBetween('end_date', [$start, $end])
                  ->orWhere(function($q2) use ($start, $end) {
                      $q2->where('start_date', '<=', $start)
                         ->where('end_date', '>=', $end);
                  });
            });
        }
        
        // Filters
        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }
        if ($request->filled('department_id')) {
            $query->whereHas('staff', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }
        if ($request->filled('user_category_id')) {
            $query->whereHas('staff', function($q) use ($request) {
                $q->where('user_category_id', $request->user_category_id);
            });
        }
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $leaveRequests = $query->get();
        
        $statusColors = [
            'pending' => '#ffc107',
            'supervisor_approved' => '#17a2b8',
            'approved' => '#28a745',
        ];
        
        $events = $leaveRequests->map(function($leave) use ($statusColors) {
            $leaveTypeName = $leave->leaveType->name ?? 'Leave';
            $baseColor = $leave->leaveType->color ?? '#667eea';
            
            // Adjust color/opacity based on status
            $color = $leave->status === 'pending' ? $statusColors['pending'] : 
                    ($leave->status === 'supervisor_approved' ? $statusColors['supervisor_approved'] : $baseColor);
            
            $borderColor = $statusColors[$leave->status] ?? $baseColor;
            
            return [
                'id' => $leave->id,
                'title' => ($leave->staff->user->name ?? 'Unknown') . ' - ' . $leaveTypeName,
                'start' => $leave->start_date,
                'end' => Carbon::parse($leave->end_date)->addDay()->format('Y-m-d'), // FullCalendar end is exclusive
                'backgroundColor' => $color,
                'borderColor' => $borderColor,
                'textColor' => '#ffffff',
                'allDay' => true,
                'extendedProps' => [
                    'leave_id' => $leave->id,
                    'staff_id' => $leave->staff_id,
                    'staff_name' => $leave->staff->user->name ?? 'Unknown',
                    'employee_id' => $leave->staff->employee_id ?? 'N/A',
                    'department' => $leave->staff->department->name ?? 'N/A',
                    'specialization' => $leave->staff->specialization->name ?? 'N/A',
                    'leave_type' => $leaveTypeName,
                    'leave_type_id' => $leave->leave_type_id,
                    'leave_type_color' => $leave->leaveType->color ?? '#667eea',
                    'start_date' => Carbon::parse($leave->start_date)->format('M d, Y'),
                    'end_date' => Carbon::parse($leave->end_date)->format('M d, Y'),
                    'start_date_raw' => Carbon::parse($leave->start_date)->format('Y-m-d'),
                    'end_date_raw' => Carbon::parse($leave->end_date)->format('Y-m-d'),
                    'total_days' => $leave->total_days,
                    'status' => $leave->status,
                    'status_label' => ucwords(str_replace('_', ' ', $leave->status)),
                    'reason' => $leave->reason,
                    'is_half_day' => $leave->is_half_day,
                    'relief_staff' => $leave->reliefStaff->user->name ?? null,
                ],
            ];
        });
        
        return response()->json($events);
    }

    /**
     * Get calendar statistics
     */
    public function stats(Request $request)
    {
        return response()->json($this->getCalendarStats($request->input('date')));
    }

    /**
     * Get staff currently on leave
     */
    public function onLeaveToday(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        
        $onLeave = LeaveRequest::with(['staff.user', 'staff.department', 'leaveType'])
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->get();
        
        return response()->json([
            'date' => $date,
            'count' => $onLeave->count(),
            'staff' => $onLeave->map(function($leave) {
                return [
                    'id' => $leave->staff_id,
                    'name' => $leave->staff->user->name ?? 'Unknown',
                    'employee_id' => $leave->staff->employee_id ?? 'N/A',
                    'department' => $leave->staff->department->name ?? 'N/A',
                    'leave_type' => $leave->leaveType->name ?? 'N/A',
                    'return_date' => Carbon::parse($leave->end_date)->addDay()->format('M d, Y'),
                ];
            }),
        ]);
    }

    /**
     * Detect leave conflicts (multiple staff off same day)
     */
    public function conflicts(Request $request)
    {
        $start = $request->input('start', now()->startOfMonth()->toDateString());
        $end = $request->input('end', now()->endOfMonth()->toDateString());
        $departmentId = $request->input('department_id');
        $threshold = $request->input('threshold', 2); // Alert if more than X people off
        
        $conflicts = [];
        $current = Carbon::parse($start);
        $endDate = Carbon::parse($end);
        
        while ($current <= $endDate) {
            $dateStr = $current->toDateString();
            
            $query = LeaveRequest::with(['staff.user', 'staff.department'])
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr);
            
            if ($departmentId) {
                $query->whereHas('staff', function($q) use ($departmentId) {
                    $q->where('department_id', $departmentId);
                });
            }
            
            $count = $query->count();
            
            if ($count >= $threshold) {
                $leaves = $query->get();
                $conflicts[] = [
                    'date' => $dateStr,
                    'formatted_date' => $current->format('D, M d'),
                    'count' => $count,
                    'staff' => $leaves->map(function($leave) {
                        return [
                            'name' => $leave->staff->user->name ?? 'Unknown',
                            'department' => $leave->staff->department->name ?? 'N/A',
                        ];
                    }),
                ];
            }
            
            $current->addDay();
        }
        
        return response()->json([
            'threshold' => $threshold,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Get leave summary by department
     */
    public function departmentSummary(Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month');
        
        $query = LeaveRequest::with(['staff.department'])
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereYear('start_date', $year);
        
        if ($month) {
            $query->whereMonth('start_date', $month);
        }
        
        $leaves = $query->get();
        
        $summary = $leaves->groupBy(function($leave) {
            return $leave->staff->department->name ?? 'Unknown';
        })->map(function($group, $deptName) {
            return [
                'department' => $deptName,
                'total_requests' => $group->count(),
                'total_days' => $group->sum('total_days'),
                'unique_staff' => $group->pluck('staff_id')->unique()->count(),
            ];
        })->values();
        
        return response()->json($summary);
    }

    /**
     * Get monthly heatmap data
     */
    public function heatmap(Request $request)
    {
        $year = $request->input('year', now()->year);
        
        $leaves = LeaveRequest::where('status', LeaveRequest::STATUS_APPROVED)
            ->whereYear('start_date', $year)
            ->get();
        
        $heatmap = [];
        
        foreach ($leaves as $leave) {
            $current = Carbon::parse($leave->start_date);
            $end = Carbon::parse($leave->end_date);
            
            while ($current <= $end) {
                $dateStr = $current->toDateString();
                if (!isset($heatmap[$dateStr])) {
                    $heatmap[$dateStr] = 0;
                }
                $heatmap[$dateStr]++;
                $current->addDay();
            }
        }
        
        return response()->json($heatmap);
    }

    /**
     * Get internal stats
     */
    private function getCalendarStats(?string $date = null)
    {
        $date = $date ? Carbon::parse($date) : now();
        
        return [
            'on_leave_today' => LeaveRequest::where('status', LeaveRequest::STATUS_APPROVED)
                ->where('start_date', '<=', $date->toDateString())
                ->where('end_date', '>=', $date->toDateString())
                ->count(),
            'pending_requests' => LeaveRequest::whereIn('status', [
                LeaveRequest::STATUS_PENDING,
                LeaveRequest::STATUS_SUPERVISOR_APPROVED,
            ])->count(),
            'approved_this_month' => LeaveRequest::where('status', LeaveRequest::STATUS_APPROVED)
                ->whereMonth('reviewed_at', $date->month)
                ->whereYear('reviewed_at', $date->year)
                ->count(),
            'total_days_this_month' => LeaveRequest::where('status', LeaveRequest::STATUS_APPROVED)
                ->whereMonth('start_date', $date->month)
                ->whereYear('start_date', $date->year)
                ->sum('total_days'),
            'upcoming_leaves' => LeaveRequest::where('status', LeaveRequest::STATUS_APPROVED)
                ->where('start_date', '>', $date->toDateString())
                ->where('start_date', '<=', $date->copy()->addDays(7)->toDateString())
                ->count(),
        ];
    }
}
