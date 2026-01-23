<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\PayHead;
use App\Models\HR\StaffSalaryProfile;
use App\Models\HR\StaffSalaryProfileItem;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * HRMS Implementation Plan - Section 7.2
 * Salary Profile Controller
 */
class SalaryProfileController extends Controller
{
    public function index(Request $request)
    {
        $query = StaffSalaryProfile::with(['staff.user', 'createdBy'])
            ->withCount('items');

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $profiles = $query->latest()->paginate(20);
        $staffList = Staff::active()->with('user')->get();

        return view('admin.hr.salary-profiles.index', compact('profiles', 'staffList'));
    }

    public function create(Request $request)
    {
        $staffList = Staff::active()->with('user')->get();
        $selectedStaff = $request->staff_id ? Staff::with('user')->find($request->staff_id) : null;
        $payHeads = PayHead::active()->orderBy('type')->orderBy('sort_order')->get();
        $payFrequencies = StaffSalaryProfile::getPayFrequencies();

        return view('admin.hr.salary-profiles.create', compact('staffList', 'selectedStaff', 'payHeads', 'payFrequencies'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:staff,id',
            'basic_salary' => 'required|numeric|min:0',
            'pay_frequency' => 'required|in:monthly,bi_weekly,weekly',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
            // Pay head items
            'items' => 'nullable|array',
            'items.*.pay_head_id' => 'required|exists:pay_heads,id',
            'items.*.calculation_type' => 'required|in:fixed,percentage',
            'items.*.calculation_base' => 'nullable|in:basic_salary,gross_salary',
            'items.*.value' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::transaction(function () use ($request) {
                // Deactivate current profile if creating a new active one
                if ($request->boolean('is_active', true)) {
                    StaffSalaryProfile::where('staff_id', $request->staff_id)
                        ->where('is_active', true)
                        ->update(['is_active' => false, 'effective_to' => $request->effective_from]);
                }

                // Create profile
                $profile = StaffSalaryProfile::create([
                    'staff_id' => $request->staff_id,
                    'basic_salary' => $request->basic_salary,
                    'pay_frequency' => $request->pay_frequency,
                    'effective_from' => $request->effective_from,
                    'effective_to' => $request->effective_to,
                    'is_active' => $request->boolean('is_active', true),
                    'notes' => $request->notes,
                    'created_by' => auth()->id(),
                ]);

                // Create pay head items
                if ($request->filled('items')) {
                    foreach ($request->items as $item) {
                        if (!empty($item['pay_head_id']) && isset($item['value'])) {
                            StaffSalaryProfileItem::create([
                                'salary_profile_id' => $profile->id,
                                'pay_head_id' => $item['pay_head_id'],
                                'calculation_type' => $item['calculation_type'],
                                'calculation_base' => $item['calculation_base'] ?? null,
                                'value' => $item['value'],
                            ]);
                        }
                    }
                }
            });

            return redirect()->route('hr.salary-profiles.index')
                ->with('success', 'Salary profile created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(StaffSalaryProfile $salaryProfile)
    {
        $salaryProfile->load(['staff.user', 'createdBy', 'items.payHead']);

        // Calculate salary breakdown
        $breakdown = [
            'basic_salary' => $salaryProfile->basic_salary,
            'gross_salary' => $salaryProfile->calculateGrossSalary(),
            'total_deductions' => $salaryProfile->calculateTotalDeductions(),
            'net_salary' => $salaryProfile->calculateNetSalary(),
        ];

        return view('admin.hr.salary-profiles.show', compact('salaryProfile', 'breakdown'));
    }

    public function edit(StaffSalaryProfile $salaryProfile)
    {
        $salaryProfile->load('items.payHead');
        $payHeads = PayHead::active()->orderBy('type')->orderBy('sort_order')->get();
        $payFrequencies = StaffSalaryProfile::getPayFrequencies();

        return view('admin.hr.salary-profiles.edit', compact('salaryProfile', 'payHeads', 'payFrequencies'));
    }

    public function update(Request $request, StaffSalaryProfile $salaryProfile)
    {
        $validator = Validator::make($request->all(), [
            'basic_salary' => 'required|numeric|min:0',
            'pay_frequency' => 'required|in:monthly,bi_weekly,weekly',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
            'items.*.pay_head_id' => 'required|exists:pay_heads,id',
            'items.*.calculation_type' => 'required|in:fixed,percentage',
            'items.*.calculation_base' => 'nullable|in:basic_salary,gross_salary',
            'items.*.value' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::transaction(function () use ($request, $salaryProfile) {
                // Update profile
                $salaryProfile->update([
                    'basic_salary' => $request->basic_salary,
                    'pay_frequency' => $request->pay_frequency,
                    'effective_from' => $request->effective_from,
                    'effective_to' => $request->effective_to,
                    'is_active' => $request->boolean('is_active'),
                    'notes' => $request->notes,
                ]);

                // Remove existing items and recreate
                $salaryProfile->items()->delete();

                if ($request->filled('items')) {
                    foreach ($request->items as $item) {
                        if (!empty($item['pay_head_id']) && isset($item['value'])) {
                            StaffSalaryProfileItem::create([
                                'salary_profile_id' => $salaryProfile->id,
                                'pay_head_id' => $item['pay_head_id'],
                                'calculation_type' => $item['calculation_type'],
                                'calculation_base' => $item['calculation_base'] ?? null,
                                'value' => $item['value'],
                            ]);
                        }
                    }
                }
            });

            return redirect()->route('hr.salary-profiles.show', $salaryProfile)
                ->with('success', 'Salary profile updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(StaffSalaryProfile $salaryProfile)
    {
        // Check if profile has been used in payroll
        if ($salaryProfile->payrollItems()->exists()) {
            return back()->with('error', 'Cannot delete salary profile that has been used in payroll processing.');
        }

        $salaryProfile->items()->delete();
        $salaryProfile->delete();

        return redirect()->route('hr.salary-profiles.index')
            ->with('success', 'Salary profile deleted successfully.');
    }

    public function staffProfiles(Staff $staff)
    {
        $profiles = StaffSalaryProfile::where('staff_id', $staff->id)
            ->with(['items.payHead', 'createdBy'])
            ->orderBy('effective_from', 'desc')
            ->get();

        return view('admin.hr.salary-profiles.staff', compact('staff', 'profiles'));
    }
}
