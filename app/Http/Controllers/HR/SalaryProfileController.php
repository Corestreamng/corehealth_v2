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
        // Return stats if requested
        if ($request->has('stats')) {
            return response()->json($this->getSalaryProfileStats());
        }

        // Handle DataTable AJAX request
        if ($request->ajax()) {
            $query = StaffSalaryProfile::with(['staff.user', 'createdBy'])
                ->withCount('items');

            return datatables()->of($query)
                ->addIndexColumn()
                ->addColumn('staff_name', function ($profile) {
                    $user = $profile->staff->user ?? null;
                    return $user ? $user->firstname . ' ' . $user->surname : 'N/A';
                })
                ->addColumn('employee_id', function ($profile) {
                    return $profile->staff->employee_id ?? 'N/A';
                })
                ->addColumn('basic_salary_formatted', function ($profile) {
                    return '₦' . number_format($profile->basic_salary, 2);
                })
                ->addColumn('gross_salary_formatted', function ($profile) {
                    return '₦' . number_format($profile->gross_salary ?? $profile->basic_salary, 2);
                })
                ->addColumn('total_deductions_formatted', function ($profile) {
                    return '₦' . number_format($profile->total_deductions ?? 0, 2);
                })
                ->addColumn('net_salary_formatted', function ($profile) {
                    return '₦' . number_format($profile->net_salary ?? $profile->basic_salary, 2);
                })
                ->addColumn('is_current', function ($profile) {
                    return $profile->is_active;
                })
                ->addColumn('action', function ($profile) {
                    $viewBtn = '<button type="button" class="btn btn-sm btn-info view-btn mr-1" data-id="' . $profile->id . '" title="View"><i class="mdi mdi-eye"></i></button>';
                    $editBtn = '<button type="button" class="btn btn-sm btn-primary edit-btn mr-1" data-id="' . $profile->id . '" title="Edit"><i class="mdi mdi-pencil"></i></button>';
                    $deleteBtn = '<button type="button" class="btn btn-sm btn-danger delete-btn" data-id="' . $profile->id . '" title="Delete"><i class="mdi mdi-delete"></i></button>';
                    return $viewBtn . $editBtn . $deleteBtn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $staffList = Staff::active()->with('user')->get();
        $payHeads = PayHead::active()->orderBy('type')->orderBy('sort_order')->get();
        $payFrequencies = StaffSalaryProfile::getPayFrequencies();
        $stats = $this->getSalaryProfileStats();

        return view('admin.hr.salary-profiles.index', compact('staffList', 'payHeads', 'payFrequencies', 'stats'));
    }

    /**
     * Get salary profile statistics
     */
    protected function getSalaryProfileStats(): array
    {
        $activeProfiles = StaffSalaryProfile::where('is_active', true);
        $allProfiles = StaffSalaryProfile::query();
        
        // Count active staff without salary profiles
        $staffWithProfiles = StaffSalaryProfile::where('is_active', true)->pluck('staff_id')->unique();
        $activeStaffCount = Staff::active()->count();
        $staffWithoutProfiles = $activeStaffCount - $staffWithProfiles->count();

        // Calculate totals from active profiles
        $totalBasic = (clone $activeProfiles)->sum('basic_salary');
        $totalGross = (clone $activeProfiles)->sum('gross_salary') ?: $totalBasic;
        $totalDeductions = (clone $activeProfiles)->sum('total_deductions');
        $totalNet = (clone $activeProfiles)->sum('net_salary') ?: ($totalGross - $totalDeductions);

        // Average salary
        $profileCount = (clone $activeProfiles)->count();
        $avgNet = $profileCount > 0 ? $totalNet / $profileCount : 0;

        return [
            'total_profiles' => $allProfiles->count(),
            'active_profiles' => $profileCount,
            'staff_without_profiles' => max(0, $staffWithoutProfiles),
            'total_active_staff' => $activeStaffCount,
            'total_basic' => $totalBasic,
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net' => $totalNet,
            'avg_net_salary' => $avgNet,
            'total_basic_formatted' => '₦' . number_format($totalBasic, 2),
            'total_gross_formatted' => '₦' . number_format($totalGross, 2),
            'total_deductions_formatted' => '₦' . number_format($totalDeductions, 2),
            'total_net_formatted' => '₦' . number_format($totalNet, 2),
            'avg_net_formatted' => '₦' . number_format($avgNet, 2),
        ];
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
            'effective_date' => 'required|date',
            'items' => 'nullable|array',
            'items.*.pay_head_id' => 'required|exists:pay_heads,id',
            'items.*.calculation_type' => 'required|in:fixed,percentage',
            'items.*.calculation_base' => 'nullable|in:basic,gross,basic_salary,gross_salary',
            'items.*.value' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        // Check for duplicate pay heads
        if ($request->filled('items')) {
            $payHeadIds = collect($request->items)
                ->filter(fn($item) => !empty($item['pay_head_id']))
                ->pluck('pay_head_id')
                ->toArray();
            
            $duplicates = array_diff_assoc($payHeadIds, array_unique($payHeadIds));
            
            if (!empty($duplicates)) {
                $duplicateNames = PayHead::whereIn('id', array_unique($duplicates))->pluck('name')->implode(', ');
                $errorMessage = "Duplicate pay head(s) detected: {$duplicateNames}. Each pay head can only be added once per salary profile.";
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                        'errors' => ['items' => [$errorMessage]]
                    ], 422);
                }
                return back()->withErrors(['items' => $errorMessage])->withInput();
            }
        }

        try {
            $profile = DB::transaction(function () use ($request) {
                // Deactivate current profile if creating a new active one
                StaffSalaryProfile::where('staff_id', $request->staff_id)
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'effective_to' => $request->effective_date]);

                // Create profile
                $profile = StaffSalaryProfile::create([
                    'staff_id' => $request->staff_id,
                    'basic_salary' => $request->basic_salary,
                    'pay_frequency' => 'monthly',
                    'effective_from' => $request->effective_date,
                    'is_active' => true,
                    'created_by' => auth()->id(),
                ]);

                // Create pay head items with calculation config
                if ($request->filled('items')) {
                    foreach ($request->items as $item) {
                        if (!empty($item['pay_head_id']) && isset($item['value'])) {
                            // Normalize calculation_base values
                            $calcBase = $item['calculation_base'] ?? null;
                            if ($calcBase === 'basic') $calcBase = 'basic_salary';
                            if ($calcBase === 'gross') $calcBase = 'gross_salary';

                            StaffSalaryProfileItem::create([
                                'salary_profile_id' => $profile->id,
                                'pay_head_id' => $item['pay_head_id'],
                                'calculation_type' => $item['calculation_type'] ?? 'fixed',
                                'calculation_base' => $calcBase,
                                'value' => $item['value'],
                            ]);
                        }
                    }
                }

                return $profile;
            });

            // Calculate and cache salary values
            $profile->load('items.payHead');
            $profile->gross_salary = $profile->calculateGrossSalary();
            $profile->total_deductions = $profile->calculateTotalDeductions();
            $profile->net_salary = $profile->calculateNetSalary();
            $profile->save();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Salary profile created successfully',
                    'data' => $profile
                ]);
            }

            return redirect()->route('hr.salary-profiles.index')
                ->with('success', 'Salary profile created successfully.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(Request $request, StaffSalaryProfile $salaryProfile)
    {
        $salaryProfile->load(['staff.user', 'createdBy', 'items.payHead']);

        if ($request->ajax()) {
            $user = $salaryProfile->staff->user ?? null;
            return response()->json([
                'id' => $salaryProfile->id,
                'staff_id' => $salaryProfile->staff_id,
                'staff_name' => $user ? $user->firstname . ' ' . $user->surname : 'N/A',
                'employee_id' => $salaryProfile->staff->employee_id ?? 'N/A',
                'basic_salary' => $salaryProfile->basic_salary,
                'gross_salary' => $salaryProfile->gross_salary ?? $salaryProfile->calculateGrossSalary(),
                'total_deductions' => $salaryProfile->total_deductions ?? $salaryProfile->calculateTotalDeductions(),
                'net_salary' => $salaryProfile->net_salary ?? $salaryProfile->calculateNetSalary(),
                'effective_date' => $salaryProfile->effective_from ? $salaryProfile->effective_from->format('Y-m-d') : null,
                'is_current' => $salaryProfile->is_active,
                'items' => $salaryProfile->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'pay_head_id' => $item->pay_head_id,
                        'pay_head' => $item->payHead,
                        'calculation_type' => $item->calculation_type,
                        'calculation_base' => $item->calculation_base,
                        'value' => $item->value,
                    ];
                }),
            ]);
        }

        // Calculate salary breakdown
        $breakdown = [
            'basic_salary' => $salaryProfile->basic_salary,
            'gross_salary' => $salaryProfile->calculateGrossSalary(),
            'total_deductions' => $salaryProfile->calculateTotalDeductions(),
            'net_salary' => $salaryProfile->calculateNetSalary(),
        ];

        return view('admin.hr.salary-profiles.show', compact('salaryProfile', 'breakdown'));
    }

    public function edit(Request $request, StaffSalaryProfile $salaryProfile)
    {
        $salaryProfile->load('items.payHead');

        if ($request->ajax()) {
            return response()->json([
                'id' => $salaryProfile->id,
                'staff_id' => $salaryProfile->staff_id,
                'basic_salary' => $salaryProfile->basic_salary,
                'effective_date' => $salaryProfile->effective_from ? $salaryProfile->effective_from->format('Y-m-d') : null,
                'items' => $salaryProfile->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'pay_head_id' => $item->pay_head_id,
                        'pay_head' => $item->payHead,
                        'calculation_type' => $item->calculation_type,
                        'calculation_base' => $item->calculation_base,
                        'value' => $item->value,
                    ];
                }),
            ]);
        }

        $payHeads = PayHead::active()->orderBy('type')->orderBy('sort_order')->get();
        $payFrequencies = StaffSalaryProfile::getPayFrequencies();

        return view('admin.hr.salary-profiles.edit', compact('salaryProfile', 'payHeads', 'payFrequencies'));
    }

    public function update(Request $request, StaffSalaryProfile $salaryProfile)
    {
        $validator = Validator::make($request->all(), [
            'basic_salary' => 'required|numeric|min:0',
            'effective_date' => 'required|date',
            'items' => 'nullable|array',
            'items.*.pay_head_id' => 'required|exists:pay_heads,id',
            'items.*.calculation_type' => 'required|in:fixed,percentage',
            'items.*.calculation_base' => 'nullable|in:basic,gross,basic_salary,gross_salary',
            'items.*.value' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        // Check for duplicate pay heads
        if ($request->filled('items')) {
            $payHeadIds = collect($request->items)
                ->filter(fn($item) => !empty($item['pay_head_id']))
                ->pluck('pay_head_id')
                ->toArray();
            
            $duplicates = array_diff_assoc($payHeadIds, array_unique($payHeadIds));
            
            if (!empty($duplicates)) {
                $duplicateNames = PayHead::whereIn('id', array_unique($duplicates))->pluck('name')->implode(', ');
                $errorMessage = "Duplicate pay head(s) detected: {$duplicateNames}. Each pay head can only be added once per salary profile.";
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                        'errors' => ['items' => [$errorMessage]]
                    ], 422);
                }
                return back()->withErrors(['items' => $errorMessage])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($request, $salaryProfile) {
                // Update profile
                $salaryProfile->update([
                    'basic_salary' => $request->basic_salary,
                    'effective_from' => $request->effective_date,
                ]);

                // Remove existing items and recreate
                $salaryProfile->items()->delete();

                if ($request->filled('items')) {
                    foreach ($request->items as $item) {
                        if (!empty($item['pay_head_id']) && isset($item['value'])) {
                            // Normalize calculation_base values
                            $calcBase = $item['calculation_base'] ?? null;
                            if ($calcBase === 'basic') $calcBase = 'basic_salary';
                            if ($calcBase === 'gross') $calcBase = 'gross_salary';

                            StaffSalaryProfileItem::create([
                                'salary_profile_id' => $salaryProfile->id,
                                'pay_head_id' => $item['pay_head_id'],
                                'calculation_type' => $item['calculation_type'] ?? 'fixed',
                                'calculation_base' => $calcBase,
                                'value' => $item['value'],
                            ]);
                        }
                    }
                }
            });

            // Recalculate and cache salary values
            $salaryProfile->load('items.payHead');
            $salaryProfile->gross_salary = $salaryProfile->calculateGrossSalary();
            $salaryProfile->total_deductions = $salaryProfile->calculateTotalDeductions();
            $salaryProfile->net_salary = $salaryProfile->calculateNetSalary();
            $salaryProfile->save();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Salary profile updated successfully',
                    'data' => $salaryProfile
                ]);
            }

            return redirect()->route('hr.salary-profiles.show', $salaryProfile)
                ->with('success', 'Salary profile updated successfully.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(Request $request, StaffSalaryProfile $salaryProfile)
    {
        // Check if profile has been used in payroll
        if ($salaryProfile->payrollItems()->exists()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete salary profile that has been used in payroll processing.'
                ], 422);
            }
            return back()->with('error', 'Cannot delete salary profile that has been used in payroll processing.');
        }

        $salaryProfile->items()->delete();
        $salaryProfile->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Salary profile deleted successfully.'
            ]);
        }

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
