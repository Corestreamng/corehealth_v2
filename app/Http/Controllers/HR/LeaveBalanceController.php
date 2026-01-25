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

        // Handle DataTable AJAX request
        if ($request->ajax() || $request->wantsJson()) {
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $searchValue = $request->input('search.value');

            // Search
            if ($searchValue) {
                $query->where(function($q) use ($searchValue) {
                    $q->whereHas('staff.user', function($q) use ($searchValue) {
                        $q->where('firstname', 'like', "%{$searchValue}%")
                          ->orWhere('surname', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('staff', function($q) use ($searchValue) {
                        $q->where('employee_id', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('leaveType', function($q) use ($searchValue) {
                        $q->where('name', 'like', "%{$searchValue}%");
                    });
                });
            }

            $totalRecords = LeaveBalance::where('year', $year)->count();
            $filteredRecords = $query->count();

            $balances = $query->skip($start)->take($length)->get();

            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $balances->map(function($balance, $index) use ($start) {
                    $available = $balance->entitled_days - $balance->used_days - $balance->pending_days + $balance->carried_forward;

                    return [
                        'DT_RowIndex' => $start + $index + 1,
                        'staff_name' => $balance->staff->user->name ?? 'N/A',
                        'employee_id' => $balance->staff->employee_id ?? 'N/A',
                        'leave_type' => $balance->leaveType->name ?? 'N/A',
                        'entitled_days' => number_format($balance->entitled_days, 1),
                        'used_days' => number_format($balance->used_days, 1),
                        'pending_days' => number_format($balance->pending_days, 1),
                        'carried_forward' => number_format($balance->carried_forward, 1),
                        'available' => $available,
                        'action' => '<button type="button" class="btn btn-sm btn-outline-primary adjust-btn"
                                        data-id="'.$balance->id.'"
                                        data-staff="'.$balance->staff->user->name.'"
                                        data-type="'.$balance->leaveType->name.'"
                                        data-available="'.number_format($available, 1).'"
                                        style="border-radius: 6px;">
                                        <i class="mdi mdi-plus-minus"></i>
                                    </button>',
                    ];
                })
            ]);
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
