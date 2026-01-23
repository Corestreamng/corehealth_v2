<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveType;
use App\Models\Staff;
use App\Services\LeaveService;
use Illuminate\Http\Request;

/**
 * HRMS Implementation Plan - Section 7.2
 * Leave Balance Controller
 */
class LeaveBalanceController extends Controller
{
    protected LeaveService $leaveService;

    public function __construct(LeaveService $leaveService)
    {
        $this->leaveService = $leaveService;
    }

    public function index(Request $request)
    {
        $year = $request->year ?? now()->year;

        $query = LeaveBalance::with(['staff.user', 'leaveType'])
            ->where('year', $year);

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        $balances = $query->orderBy('staff_id')
            ->orderBy('leave_type_id')
            ->paginate(50);

        $leaveTypes = LeaveType::active()->orderBy('name')->get();
        $staffList = Staff::active()->with('user')->get();
        $years = range(now()->year - 2, now()->year + 1);

        return view('admin.hr.leave-balances.index', compact('balances', 'leaveTypes', 'staffList', 'years', 'year'));
    }

    public function show(Staff $staff, Request $request)
    {
        $year = $request->year ?? now()->year;

        $balances = LeaveBalance::with('leaveType')
            ->where('staff_id', $staff->id)
            ->where('year', $year)
            ->get();

        // Get all active leave types and ensure balances exist
        $leaveTypes = LeaveType::active()->get();
        foreach ($leaveTypes as $leaveType) {
            if (!$balances->contains('leave_type_id', $leaveType->id)) {
                $balance = $this->leaveService->getOrCreateBalance($staff->id, $leaveType->id, $year);
                $balances->push($balance);
            }
        }

        $years = range(now()->year - 2, now()->year + 1);

        return view('admin.hr.leave-balances.show', compact('staff', 'balances', 'years', 'year'));
    }

    public function initializeYear(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2050',
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'exists:staff,id',
        ]);

        $count = $this->leaveService->initializeYearBalances(
            $request->year,
            $request->staff_ids
        );

        return back()->with('success', "Initialized {$count} leave balances for year {$request->year}.");
    }

    public function adjust(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'year' => 'required|integer',
            'adjustment' => 'required|numeric|min:-365|max:365',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $balance = $this->leaveService->adjustBalance(
                $request->staff_id,
                $request->leave_type_id,
                $request->year,
                $request->adjustment,
                $request->reason
            );

            return back()->with('success', 'Leave balance adjusted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
