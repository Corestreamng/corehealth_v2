<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * HRMS Implementation Plan - Section 7.2
 * Leave Type Controller - CRUD for leave types
 */
class LeaveTypeController extends Controller
{
    public function index()
    {
        $leaveTypes = LeaveType::withCount('leaveRequests')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.hr.leave-types.index', compact('leaveTypes'));
    }

    public function create()
    {
        return view('admin.hr.leave-types.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:leave_types,name',
            'code' => 'required|string|max:20|unique:leave_types,code',
            'description' => 'nullable|string|max:1000',
            'max_days_per_year' => 'required|numeric|min:0|max:365',
            'max_consecutive_days' => 'nullable|numeric|min:0|max:365',
            'max_requests_per_year' => 'nullable|integer|min:1|max:50',
            'min_days_notice' => 'nullable|integer|min:0|max:90',
            'requires_attachment' => 'boolean',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
            'color' => 'nullable|string|max:20',
            'applicable_employment_types' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        LeaveType::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'max_days_per_year' => $request->max_days_per_year,
            'max_consecutive_days' => $request->max_consecutive_days,
            'max_requests_per_year' => $request->max_requests_per_year,
            'min_days_notice' => $request->min_days_notice,
            'requires_attachment' => $request->boolean('requires_attachment'),
            'is_paid' => $request->boolean('is_paid', true),
            'is_active' => $request->boolean('is_active', true),
            'color' => $request->color ?? '#3498db',
            'applicable_employment_types' => $request->applicable_employment_types,
        ]);

        return redirect()->route('hr.leave-types.index')
            ->with('success', 'Leave type created successfully.');
    }

    public function show(LeaveType $leaveType)
    {
        $leaveType->load(['leaveRequests' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return view('admin.hr.leave-types.show', compact('leaveType'));
    }

    public function edit(LeaveType $leaveType)
    {
        return view('admin.hr.leave-types.edit', compact('leaveType'));
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:leave_types,name,' . $leaveType->id,
            'code' => 'required|string|max:20|unique:leave_types,code,' . $leaveType->id,
            'description' => 'nullable|string|max:1000',
            'max_days_per_year' => 'required|numeric|min:0|max:365',
            'max_consecutive_days' => 'nullable|numeric|min:0|max:365',
            'max_requests_per_year' => 'nullable|integer|min:1|max:50',
            'min_days_notice' => 'nullable|integer|min:0|max:90',
            'requires_attachment' => 'boolean',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
            'color' => 'nullable|string|max:20',
            'applicable_employment_types' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $leaveType->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'max_days_per_year' => $request->max_days_per_year,
            'max_consecutive_days' => $request->max_consecutive_days,
            'max_requests_per_year' => $request->max_requests_per_year,
            'min_days_notice' => $request->min_days_notice,
            'requires_attachment' => $request->boolean('requires_attachment'),
            'is_paid' => $request->boolean('is_paid'),
            'is_active' => $request->boolean('is_active'),
            'color' => $request->color,
            'applicable_employment_types' => $request->applicable_employment_types,
        ]);

        return redirect()->route('hr.leave-types.index')
            ->with('success', 'Leave type updated successfully.');
    }

    public function destroy(LeaveType $leaveType)
    {
        // Check if leave type has been used
        if ($leaveType->leaveRequests()->exists()) {
            return back()->with('error', 'Cannot delete leave type that has been used. Consider deactivating it instead.');
        }

        $leaveType->delete();

        return redirect()->route('hr.leave-types.index')
            ->with('success', 'Leave type deleted successfully.');
    }
}
