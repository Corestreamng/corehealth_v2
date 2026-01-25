<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

/**
 * HRMS Implementation Plan - Section 7.2
 * Leave Type Controller - CRUD for leave types
 */
class LeaveTypeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $leaveTypes = LeaveType::query();

            return DataTables::of($leaveTypes)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $buttons = '<div class="btn-group">';
                    $buttons .= '<button type="button" class="btn btn-sm btn-light edit-btn"
                        data-id="' . $row->id . '"
                        data-name="' . e($row->name) . '"
                        data-code="' . e($row->code) . '"
                        data-description="' . e($row->description) . '"
                        data-max_days_per_year="' . $row->max_days_per_year . '"
                        data-max_consecutive_days="' . $row->max_consecutive_days . '"
                        data-min_notice_days="' . $row->min_days_notice . '"
                        data-max_requests_per_year="' . ($row->max_requests_per_year ?? '') . '"
                        data-max_carry_forward="' . ($row->max_carry_forward ?? 0) . '"
                        data-min_service_months="' . ($row->min_service_months ?? 0) . '"
                        data-color="' . $row->color . '"
                        data-gender_specific="' . ($row->gender_specific ?? '') . '"
                        data-is_paid="' . ($row->is_paid ? 1 : 0) . '"
                        data-requires_attachment="' . ($row->requires_attachment ? 1 : 0) . '"
                        data-allow_half_day="' . ($row->allow_half_day ?? 0) . '"
                        data-allow_carry_forward="' . ($row->allow_carry_forward ?? 0) . '"
                        data-is_active="' . ($row->is_active ? 1 : 0) . '"
                        title="Edit">
                        <i class="mdi mdi-pencil"></i>
                    </button>';
                    $buttons .= '<button type="button" class="btn btn-sm btn-light delete-btn"
                        data-id="' . $row->id . '"
                        data-name="' . e($row->name) . '"
                        title="Delete">
                        <i class="mdi mdi-delete text-danger"></i>
                    </button>';
                    $buttons .= '</div>';
                    return $buttons;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('admin.hr.leave-types.index');
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
            'min_notice_days' => 'nullable|integer|min:0|max:90',
            'max_carry_forward' => 'nullable|integer|min:0|max:365',
            'min_service_months' => 'nullable|integer|min:0',
            'requires_attachment' => 'nullable',
            'is_paid' => 'nullable',
            'is_active' => 'nullable',
            'allow_half_day' => 'nullable',
            'allow_carry_forward' => 'nullable',
            'color' => 'nullable|string|max:20',
            'gender_specific' => 'nullable|string|in:male,female',
            'applicable_employment_types' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $leaveType = LeaveType::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'max_days_per_year' => $request->max_days_per_year,
            'max_consecutive_days' => $request->max_consecutive_days,
            'max_requests_per_year' => $request->max_requests_per_year,
            'min_days_notice' => $request->min_notice_days ?? 0,
            'max_carry_forward' => $request->max_carry_forward ?? 0,
            'min_service_months' => $request->min_service_months ?? 0,
            'requires_attachment' => $request->boolean('requires_attachment'),
            'is_paid' => $request->boolean('is_paid', true),
            'is_active' => $request->boolean('is_active', true),
            'allow_half_day' => $request->boolean('allow_half_day'),
            'allow_carry_forward' => $request->boolean('allow_carry_forward'),
            'color' => $request->color ?? '#3498db',
            'gender_specific' => $request->gender_specific,
            'applicable_employment_types' => $request->applicable_employment_types,
        ]);

        if ($request->ajax()) {
            return response()->json(['message' => 'Leave type created successfully.', 'data' => $leaveType]);
        }

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
            'min_notice_days' => 'nullable|integer|min:0|max:90',
            'max_carry_forward' => 'nullable|integer|min:0|max:365',
            'min_service_months' => 'nullable|integer|min:0',
            'requires_attachment' => 'nullable',
            'is_paid' => 'nullable',
            'is_active' => 'nullable',
            'allow_half_day' => 'nullable',
            'allow_carry_forward' => 'nullable',
            'color' => 'nullable|string|max:20',
            'gender_specific' => 'nullable|string|in:male,female',
            'applicable_employment_types' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $leaveType->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'max_days_per_year' => $request->max_days_per_year,
            'max_consecutive_days' => $request->max_consecutive_days,
            'max_requests_per_year' => $request->max_requests_per_year,
            'min_days_notice' => $request->min_notice_days ?? 0,
            'max_carry_forward' => $request->max_carry_forward ?? 0,
            'min_service_months' => $request->min_service_months ?? 0,
            'requires_attachment' => $request->boolean('requires_attachment'),
            'is_paid' => $request->boolean('is_paid'),
            'is_active' => $request->boolean('is_active'),
            'allow_half_day' => $request->boolean('allow_half_day'),
            'allow_carry_forward' => $request->boolean('allow_carry_forward'),
            'color' => $request->color,
            'gender_specific' => $request->gender_specific,
            'applicable_employment_types' => $request->applicable_employment_types,
        ]);

        if ($request->ajax()) {
            return response()->json(['message' => 'Leave type updated successfully.', 'data' => $leaveType]);
        }

        return redirect()->route('hr.leave-types.index')
            ->with('success', 'Leave type updated successfully.');
    }

    public function destroy(Request $request, LeaveType $leaveType)
    {
        // Check if leave type has been used
        if ($leaveType->leaveRequests()->exists()) {
            if ($request->ajax()) {
                return response()->json(['error' => 'Cannot delete leave type that has been used. Consider deactivating it instead.'], 422);
            }
            return back()->with('error', 'Cannot delete leave type that has been used. Consider deactivating it instead.');
        }

        $leaveType->delete();

        if ($request->ajax()) {
            return response()->json(['message' => 'Leave type deleted successfully.']);
        }

        return redirect()->route('hr.leave-types.index')
            ->with('success', 'Leave type deleted successfully.');
    }
}
