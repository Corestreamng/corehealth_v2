<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\PayrollBatch;
use App\Models\HR\PayrollItem;
use App\Models\Staff;
use App\Models\Department;
use App\Services\PayrollService;
use App\Services\HrAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * HRMS Implementation Plan - Section 7.2
 * Payroll Batch Controller
 */
class PayrollBatchController extends Controller
{
    protected PayrollService $payrollService;
    protected HrAttachmentService $attachmentService;

    public function __construct(PayrollService $payrollService, HrAttachmentService $attachmentService)
    {
        $this->payrollService = $payrollService;
        $this->attachmentService = $attachmentService;
    }

    /**
     * Performance-optimized staff summary for batch creation
     * Returns aggregated data by department/employment type instead of individual records
     */
    public function staffSummary(Request $request)
    {
        // Get staff with salary profiles - aggregated data only
        $staffQuery = Staff::active()->withSalaryProfile();

        // Total counts and amounts
        $totals = $staffQuery->clone()
            ->join('staff_salary_profiles', function ($join) {
                $join->on('staff.id', '=', 'staff_salary_profiles.staff_id')
                    ->where('staff_salary_profiles.is_active', true);
            })
            ->selectRaw('
                COUNT(DISTINCT staff.id) as total_staff,
                SUM(staff_salary_profiles.basic_salary) as total_basic,
                SUM(COALESCE(staff_salary_profiles.gross_salary, staff_salary_profiles.basic_salary)) as total_gross,
                SUM(COALESCE(staff_salary_profiles.total_deductions, 0)) as total_deductions
            ')
            ->first();

        $totalNet = ($totals->total_gross ?? 0) - ($totals->total_deductions ?? 0);

        // By Department
        $byDepartment = Staff::active()
            ->withSalaryProfile()
            ->join('staff_salary_profiles', function ($join) {
                $join->on('staff.id', '=', 'staff_salary_profiles.staff_id')
                    ->where('staff_salary_profiles.is_active', true);
            })
            ->leftJoin('departments', 'staff.department_id', '=', 'departments.id')
            ->groupBy('departments.id', 'departments.name')
            ->selectRaw('
                departments.id as department_id,
                COALESCE(departments.name, "Unassigned") as department_name,
                COUNT(DISTINCT staff.id) as staff_count,
                SUM(COALESCE(staff_salary_profiles.gross_salary, staff_salary_profiles.basic_salary)) as total_gross,
                SUM(COALESCE(staff_salary_profiles.total_deductions, 0)) as total_deductions
            ')
            ->orderBy('departments.name')
            ->get()
            ->map(function ($dept) {
                $dept->total_net = $dept->total_gross - $dept->total_deductions;
                return $dept;
            });

        // By Employment Type
        $byEmploymentType = Staff::active()
            ->withSalaryProfile()
            ->join('staff_salary_profiles', function ($join) {
                $join->on('staff.id', '=', 'staff_salary_profiles.staff_id')
                    ->where('staff_salary_profiles.is_active', true);
            })
            ->groupBy('staff.employment_type')
            ->selectRaw('
                COALESCE(staff.employment_type, "unspecified") as employment_type,
                COUNT(DISTINCT staff.id) as staff_count,
                SUM(COALESCE(staff_salary_profiles.gross_salary, staff_salary_profiles.basic_salary)) as total_gross,
                SUM(COALESCE(staff_salary_profiles.total_deductions, 0)) as total_deductions
            ')
            ->get()
            ->map(function ($type) {
                $type->total_net = $type->total_gross - $type->total_deductions;
                $type->employment_type_label = ucfirst(str_replace('_', ' ', $type->employment_type ?? 'Unspecified'));
                return $type;
            });

        return response()->json([
            'total_count' => $totals->total_staff ?? 0,
            'total_basic' => $totals->total_basic ?? 0,
            'total_gross' => $totals->total_gross ?? 0,
            'total_deductions' => $totals->total_deductions ?? 0,
            'total_net' => $totalNet,
            'by_department' => $byDepartment->map(function ($dept) {
                return [
                    'department' => $dept->department_name,
                    'count' => $dept->staff_count,
                    'total_gross' => $dept->total_gross,
                    'total_deductions' => $dept->total_deductions,
                    'total_net' => $dept->total_net,
                ];
            }),
            'by_employment_type' => $byEmploymentType->map(function ($type) {
                return [
                    'employment_type' => $type->employment_type,
                    'label' => $type->employment_type_label,
                    'count' => $type->staff_count,
                    'total_gross' => $type->total_gross,
                    'total_deductions' => $type->total_deductions,
                    'total_net' => $type->total_net,
                ];
            }),
        ]);
    }

    /**
     * Check for staff with existing payroll items in a given period
     */
    public function checkDuplicates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pay_period' => 'required|date_format:Y-m',
            'staff_ids' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $payPeriod = \Carbon\Carbon::createFromFormat('Y-m', $request->pay_period);
        $payPeriodStart = $payPeriod->copy()->startOfMonth()->format('Y-m-d');
        $payPeriodEnd = $payPeriod->copy()->endOfMonth()->format('Y-m-d');

        $staffIds = $request->staff_ids;
        $duplicates = $this->payrollService->checkExistingPayrollForPeriod($payPeriodStart, $payPeriodEnd, $staffIds);

        return response()->json([
            'success' => true,
            'duplicates' => $duplicates,
            'has_duplicates' => count($duplicates) > 0,
            'count' => count($duplicates),
        ]);
    }

    /**
     * Get staff list filtered by criteria (paginated for custom selection)
     */
    public function staffByCriteria(Request $request)
    {
        $query = Staff::active()
            ->withSalaryProfile()
            ->with(['user', 'currentSalaryProfile.items.payHead', 'department']);

        // Filter by department (name)
        if ($request->filled('department')) {
            $deptName = $request->department;
            if ($deptName === '') {
                // Unassigned/null department
                $query->whereNull('department_id');
            } else {
                $query->whereHas('department', function ($q) use ($deptName) {
                    $q->where('name', $deptName);
                });
            }
        }

        // Filter by employment type
        if ($request->filled('employment_type')) {
            $empType = $request->employment_type;
            if ($empType === '') {
                $query->whereNull('employment_type')->orWhere('employment_type', '');
            } else {
                $query->where('employment_type', $empType);
            }
        }

        // Search by name or employee ID
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('firstname', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%");
                    });
            });
        }

        // Paginate for performance
        $perPage = $request->get('per_page', 50);
        $staff = $query->paginate($perPage);

        $mappedStaff = $staff->getCollection()->map(function ($s) {
            $profile = $s->currentSalaryProfile;
            $user = $s->user;

            $basicSalary = $profile ? (float) $profile->basic_salary : 0;
            $grossSalary = $profile ? (float) ($profile->gross_salary ?? $profile->calculateGrossSalary()) : 0;
            $totalDeductions = $profile ? (float) ($profile->total_deductions ?? $profile->calculateTotalDeductions()) : 0;
            $netSalary = $grossSalary - $totalDeductions;

            return [
                'id' => $s->id,
                'employee_id' => $s->employee_id,
                'name' => $user ? $user->firstname . ' ' . $user->surname : 'N/A',
                'department_id' => $s->department_id,
                'department' => $s->department?->name ?? 'N/A',
                'employment_type' => $s->employment_type,
                'basic_salary' => $basicSalary,
                'gross_salary' => $grossSalary,
                'total_deductions' => $totalDeductions,
                'net_salary' => $netSalary,
            ];
        });

        return response()->json([
            'data' => $mappedStaff,
            'current_page' => $staff->currentPage(),
            'last_page' => $staff->lastPage(),
            'per_page' => $staff->perPage(),
            'total' => $staff->total(),
            'from' => $staff->firstItem(),
            'to' => $staff->lastItem(),
        ]);
    }

    public function index(Request $request)
    {
        // Return stats if requested
        if ($request->has('stats')) {
            return response()->json($this->payrollService->getPayrollStats());
        }

        // Handle DataTable AJAX request
        if ($request->ajax()) {
            $query = PayrollBatch::with(['createdBy', 'approvedBy'])
                ->withCount('items');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('year')) {
                $query->whereYear('pay_period_start', $request->year);
            }
            if ($request->filled('month')) {
                $query->whereMonth('pay_period_start', $request->month);
            }

            return datatables()->of($query)
                ->addIndexColumn()
                ->addColumn('batch_number', function ($batch) {
                    return $batch->batch_number ?? 'BATCH-' . str_pad($batch->id, 6, '0', STR_PAD_LEFT);
                })
                ->addColumn('pay_period_formatted', function ($batch) {
                    if ($batch->pay_period_start) {
                        return $batch->pay_period_start->format('M Y');
                    }
                    return 'N/A';
                })
                ->addColumn('staff_count', function ($batch) {
                    return $batch->items_count ?? 0;
                })
                ->addColumn('total_amount_formatted', function ($batch) {
                    return '₦' . number_format($batch->total_net ?? 0, 2);
                })
                ->addColumn('status_badge', function ($batch) {
                    $statusColors = [
                        'draft' => 'secondary',
                        'submitted' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'paid' => 'primary',
                    ];
                    $color = $statusColors[$batch->status] ?? 'secondary';
                    return '<span class="badge badge-' . $color . '">' . ucfirst($batch->status) . '</span>';
                })
                ->addColumn('created_at', function ($batch) {
                    return $batch->created_at->format('d M Y');
                })
                ->addColumn('action', function ($batch) {
                    return '<button class="btn btn-sm btn-outline-primary view-batch" data-id="' . $batch->id . '" title="View"><i class="mdi mdi-eye"></i></button>';
                })
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        // Non-AJAX: Return view
        $query = PayrollBatch::with(['createdBy', 'approvedBy'])
            ->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('year')) {
            $query->whereYear('pay_period_start', $request->year);
        }
        if ($request->filled('month')) {
            $query->whereMonth('pay_period_start', $request->month);
        }

        $batches = $query->latest()->paginate(20);
        $statuses = PayrollBatch::getStatuses();
        $stats = $this->payrollService->getPayrollStats();

        return view('admin.hr.payroll.index', compact('batches', 'statuses', 'stats'));
    }

    public function create(Request $request)
    {
        // For AJAX request - return available staff with salary profiles
        if ($request->ajax()) {
            $staffWithProfiles = Staff::active()
                ->withSalaryProfile()
                ->with(['user', 'currentSalaryProfile.items.payHead', 'department'])
                ->get()
                ->map(function ($staff) {
                    $profile = $staff->currentSalaryProfile;
                    $user = $staff->user;

                    // Calculate salary breakdown
                    $basicSalary = $profile ? (float) $profile->basic_salary : 0;
                    $grossSalary = $profile ? (float) ($profile->gross_salary ?? $profile->calculateGrossSalary()) : 0;
                    $totalDeductions = $profile ? (float) ($profile->total_deductions ?? $profile->calculateTotalDeductions()) : 0;
                    $netSalary = $grossSalary - $totalDeductions;

                    return [
                        'id' => $staff->id,
                        'employee_id' => $staff->employee_id,
                        'name' => $user ? $user->firstname . ' ' . $user->surname : 'N/A',
                        'department' => $staff->department?->name ?? 'N/A',
                        'profile_id' => $profile?->id,
                        'basic_salary' => $basicSalary,
                        'gross_salary' => $grossSalary,
                        'total_deductions' => $totalDeductions,
                        'net_salary' => $netSalary,
                        'selected' => true, // Default to selected
                    ];
                });

            // Calculate totals
            $totals = [
                'staff_count' => $staffWithProfiles->count(),
                'total_basic' => $staffWithProfiles->sum('basic_salary'),
                'total_gross' => $staffWithProfiles->sum('gross_salary'),
                'total_deductions' => $staffWithProfiles->sum('total_deductions'),
                'total_net' => $staffWithProfiles->sum('net_salary'),
            ];

            return response()->json([
                'staff' => $staffWithProfiles,
                'totals' => $totals,
            ]);
        }

        // Check for existing draft
        $existingDraft = PayrollBatch::draft()->first();
        if ($existingDraft) {
            return redirect()->route('hr.payroll.edit', $existingDraft)
                ->with('info', 'You have an existing draft payroll batch. Complete or delete it before creating a new one.');
        }

        return view('admin.hr.payroll.create');
    }

    public function store(Request $request)
    {
        // Handle AJAX request from modal form
        if ($request->ajax()) {
            $validator = Validator::make($request->all(), [
                'pay_period' => 'required|date_format:Y-m',
                'work_period_start' => 'required|date',
                'work_period_end' => 'required|date|after_or_equal:work_period_start',
                'description' => 'nullable|string|max:255',
                'selection_mode' => 'required|in:all,department,custom',
                'staff_ids' => 'nullable|array',
                'staff_ids.*' => 'exists:staff,id',
                'departments' => 'nullable|array',
                'employment_types' => 'nullable|array',
                'duplicate_action' => 'nullable|in:skip,overwrite,selective',
                'skip_staff_ids' => 'nullable|array',
                'replace_staff_ids' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            try {
                // Parse the pay_period (YYYY-MM) into start and end dates
                $payPeriod = \Carbon\Carbon::createFromFormat('Y-m', $request->pay_period);
                $payPeriodStart = $payPeriod->copy()->startOfMonth();
                $payPeriodEnd = $payPeriod->copy()->endOfMonth();

                // Parse work period dates
                $workPeriodStart = \Carbon\Carbon::parse($request->work_period_start);
                $workPeriodEnd = \Carbon\Carbon::parse($request->work_period_end);

                // Calculate days in month and days worked
                $daysInMonth = $payPeriodEnd->day;
                $daysWorked = $workPeriodStart->diffInDays($workPeriodEnd) + 1; // +1 to include both start and end

                $batchData = [
                    'name' => $request->description ?: ('Payroll - ' . $payPeriod->format('F Y')),
                    'pay_period_start' => $payPeriodStart,
                    'pay_period_end' => $payPeriodEnd,
                    'work_period_start' => $workPeriodStart,
                    'work_period_end' => $workPeriodEnd,
                    'days_in_month' => $daysInMonth,
                    'days_worked' => $daysWorked,
                ];

                $batch = $this->payrollService->createBatch($batchData, auth()->user());

                // Determine staff IDs based on selection mode
                $staffIds = null;
                $selectionMode = $request->selection_mode;

                if ($selectionMode === 'custom') {
                    // Custom selection - use provided staff_ids
                    $staffIds = $request->staff_ids;
                } elseif ($selectionMode === 'department') {
                    // Department selection - get staff by department and optionally employment type
                    $staffQuery = \App\Models\Staff::whereHas('salaryProfile', function($q) {
                        $q->where('is_active', true);
                    })->where('status', 'active');

                    if ($request->filled('departments')) {
                        $departments = collect($request->departments)->filter()->values()->all();
                        if (count($departments) > 0) {
                            // Handle empty string for 'Unassigned' department
                            $staffQuery->where(function($q) use ($departments) {
                                $q->whereIn('department', array_filter($departments, fn($d) => $d !== ''));
                                if (in_array('', $departments)) {
                                    $q->orWhereNull('department')->orWhere('department', '');
                                }
                            });
                        }
                    }

                    if ($request->filled('employment_types')) {
                        $empTypes = collect($request->employment_types)->filter()->values()->all();
                        if (count($empTypes) > 0) {
                            $staffQuery->where(function($q) use ($empTypes) {
                                $q->whereIn('employment_type', array_filter($empTypes, fn($t) => $t !== ''));
                                if (in_array('', $empTypes)) {
                                    $q->orWhereNull('employment_type')->orWhere('employment_type', '');
                                }
                            });
                        }
                    }

                    $staffIds = $staffQuery->pluck('id')->toArray();
                }
                // For 'all' mode, $staffIds remains null which will include all staff in generateBatchItems

                // Handle duplicate staff (staff with existing payroll for this period)
                $duplicateAction = $request->duplicate_action ?? 'skip';
                $skipStaffIds = $request->skip_staff_ids ?? [];
                $replaceStaffIds = $request->replace_staff_ids ?? [];

                $result = $this->payrollService->generateBatchItems($batch, $staffIds, $duplicateAction, $skipStaffIds, $replaceStaffIds);

                $message = "Payroll batch created with {$result['added']} staff members.";
                if ($result['skipped'] > 0) {
                    $message .= " ({$result['skipped']} staff skipped - already have payroll for this period)";
                }
                if ($result['replaced'] > 0) {
                    $message .= " ({$result['replaced']} staff replaced existing payroll)";
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $batch,
                    'stats' => $result
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        // Non-AJAX request (original flow)
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'payment_date' => 'nullable|date|after_or_equal:pay_period_end',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $batch = $this->payrollService->createBatch($request->all(), auth()->user());

            return redirect()->route('hr.payroll.edit', $batch)
                ->with('success', 'Payroll batch created. Now generate payroll items.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(Request $request, PayrollBatch $payrollBatch)
    {
        $payrollBatch->load([
            'items.staff.user',
            'items.staff.department',
            'items.details.payHead',
            'createdBy',
            'submittedBy',
            'approvedBy',
            'rejectedBy',
            'paidBy',
            'expense',
            'attachments.uploadedBy'
        ]);

        // Handle AJAX request
        if ($request->ajax()) {
            $user = auth()->user();

            // Build timeline events
            $timeline = [];

            // Created
            $timeline[] = [
                'title' => 'Batch Created',
                'status' => 'created',
                'user' => $payrollBatch->createdBy?->name ?? 'System',
                'date' => $payrollBatch->created_at?->format('d M Y'),
                'time' => $payrollBatch->created_at?->format('H:i'),
                'comments' => "Payroll batch created for {$payrollBatch->total_staff} staff members.",
                'completed' => true,
                'current' => false,
            ];

            // Submitted
            if ($payrollBatch->submitted_at) {
                $timeline[] = [
                    'title' => 'Submitted for Approval',
                    'status' => 'submitted',
                    'user' => $payrollBatch->submittedBy?->name ?? 'Unknown',
                    'date' => $payrollBatch->submitted_at->format('d M Y'),
                    'time' => $payrollBatch->submitted_at->format('H:i'),
                    'comments' => null,
                    'completed' => true,
                    'current' => $payrollBatch->status === 'submitted',
                ];
            } elseif ($payrollBatch->status === 'draft') {
                $timeline[] = [
                    'title' => 'Submit for Approval',
                    'status' => 'submitted',
                    'user' => null,
                    'date' => null,
                    'time' => null,
                    'comments' => null,
                    'completed' => false,
                    'current' => true,
                ];
            }

            // Approved or Rejected
            if ($payrollBatch->approved_at) {
                $timeline[] = [
                    'title' => 'Approved',
                    'status' => 'approved',
                    'user' => $payrollBatch->approvedBy?->name ?? 'Unknown',
                    'date' => $payrollBatch->approved_at->format('d M Y'),
                    'time' => $payrollBatch->approved_at->format('H:i'),
                    'comments' => $payrollBatch->approval_comments,
                    'completed' => true,
                    'current' => false,
                ];
            } elseif ($payrollBatch->rejected_at) {
                $timeline[] = [
                    'title' => 'Rejected',
                    'status' => 'rejected',
                    'user' => $payrollBatch->rejectedBy?->name ?? 'Unknown',
                    'date' => $payrollBatch->rejected_at->format('d M Y'),
                    'time' => $payrollBatch->rejected_at->format('H:i'),
                    'comments' => $payrollBatch->rejection_reason,
                    'completed' => true,
                    'current' => false,
                ];
            } elseif (in_array($payrollBatch->status, ['submitted'])) {
                $timeline[] = [
                    'title' => 'Awaiting Approval',
                    'status' => 'approval',
                    'user' => null,
                    'date' => null,
                    'time' => null,
                    'comments' => null,
                    'completed' => false,
                    'current' => true,
                ];
            }

            // Paid
            if ($payrollBatch->status === 'paid') {
                $timeline[] = [
                    'title' => 'Payment Processed',
                    'status' => 'paid',
                    'user' => $payrollBatch->paidBy?->name ?? 'Finance',
                    'date' => $payrollBatch->paid_at?->format('d M Y') ?? $payrollBatch->updated_at->format('d M Y'),
                    'time' => $payrollBatch->paid_at?->format('H:i') ?? $payrollBatch->updated_at->format('H:i'),
                    'comments' => $payrollBatch->payment_comments ?? 'All payments have been disbursed.',
                    'completed' => true,
                    'current' => false,
                ];
            } elseif ($payrollBatch->status === 'approved') {
                $timeline[] = [
                    'title' => 'Pending Payment',
                    'status' => 'paid',
                    'user' => null,
                    'date' => null,
                    'time' => null,
                    'comments' => null,
                    'completed' => false,
                    'current' => true,
                ];
            }

            return response()->json([
                'id' => $payrollBatch->id,
                'batch_number' => $payrollBatch->batch_number,
                'name' => $payrollBatch->name,
                'pay_period_formatted' => $payrollBatch->pay_period_start ? $payrollBatch->pay_period_start->format('F Y') : 'N/A',
                'pay_period_start' => $payrollBatch->pay_period_start?->format('Y-m-d'),
                'pay_period_end' => $payrollBatch->pay_period_end?->format('Y-m-d'),
                'work_period_start' => $payrollBatch->work_period_start?->format('Y-m-d'),
                'work_period_end' => $payrollBatch->work_period_end?->format('Y-m-d'),
                'work_period_formatted' => $payrollBatch->work_period_start && $payrollBatch->work_period_end
                    ? $payrollBatch->work_period_start->format('M d') . ' - ' . $payrollBatch->work_period_end->format('M d, Y')
                    : null,
                'days_in_month' => $payrollBatch->days_in_month,
                'days_worked' => $payrollBatch->days_worked,
                'is_pro_rata' => $payrollBatch->days_worked && $payrollBatch->days_in_month && $payrollBatch->days_worked < $payrollBatch->days_in_month,
                'pro_rata_percentage' => $payrollBatch->days_in_month ? round(($payrollBatch->days_worked / $payrollBatch->days_in_month) * 100, 1) : 100,
                'payment_date' => $payrollBatch->payment_date?->format('Y-m-d'),
                'status' => $payrollBatch->status,
                'status_badge' => '<span class="badge badge-' . $this->getStatusColor($payrollBatch->status) . '">' . ucfirst($payrollBatch->status) . '</span>',
                'total_staff' => $payrollBatch->total_staff,
                'total_gross' => $payrollBatch->total_gross,
                'total_additions' => $payrollBatch->total_additions,
                'total_deductions' => $payrollBatch->total_deductions,
                'total_net' => $payrollBatch->total_net,

                // Workflow info
                'created_by' => $payrollBatch->createdBy?->name ?? 'N/A',
                'created_at' => $payrollBatch->created_at?->format('d M Y H:i'),
                'submitted_by' => $payrollBatch->submittedBy?->name,
                'submitted_at' => $payrollBatch->submitted_at?->format('d M Y H:i'),
                'approved_by' => $payrollBatch->approvedBy?->name,
                'approved_at' => $payrollBatch->approved_at?->format('d M Y H:i'),
                'approval_comments' => $payrollBatch->approval_comments,
                'rejected_by' => $payrollBatch->rejectedBy?->name,
                'rejected_at' => $payrollBatch->rejected_at?->format('d M Y H:i'),
                'rejection_reason' => $payrollBatch->rejection_reason,

                // Expense link
                'expense_id' => $payrollBatch->expense_id,
                'expense_reference' => $payrollBatch->expense?->expense_number,
                'expense' => $payrollBatch->expense ? [
                    'id' => $payrollBatch->expense->id,
                    'reference' => $payrollBatch->expense->expense_number,
                    'amount' => $payrollBatch->expense->amount,
                    'status' => $payrollBatch->expense->status,
                ] : null,

                // Totals for display
                'total_gross_amount' => $payrollBatch->total_gross,
                'total_net_amount' => $payrollBatch->total_net,

                // Timeline
                'timeline' => $timeline,

                // Items summary by department
                'items_by_department' => $payrollBatch->items->groupBy(function ($item) {
                    return $item->staff?->department?->name ?? 'Unassigned';
                })->map(function ($items, $dept) {
                    return [
                        'department' => $dept,
                        'count' => $items->count(),
                        'total_net' => $items->sum('net_salary'),
                    ];
                })->values(),

                'items' => $payrollBatch->items->map(function ($item) {
                    $user = $item->staff?->user;
                    return [
                        'id' => $item->id,
                        'staff_id' => $item->staff_id,
                        'staff_name' => $user ? $user->firstname . ' ' . $user->surname : 'N/A',
                        'employee_id' => $item->staff?->employee_id ?? 'N/A',
                        'department' => $item->staff?->department?->name ?? 'N/A',
                        'days_worked' => $item->days_worked,
                        'days_in_month' => $item->days_in_month,
                        'basic_salary' => $item->basic_salary,
                        'gross_salary' => $item->gross_salary,
                        'full_gross_salary' => $item->full_gross_salary,
                        'total_additions' => $item->total_additions,
                        'total_deductions' => $item->total_deductions,
                        'net_salary' => $item->net_salary,
                        'full_net_salary' => $item->full_net_salary,
                        'bank_name' => $item->bank_name,
                        'bank_account_number' => $item->bank_account_number ? '****' . substr($item->bank_account_number, -4) : null,
                    ];
                }),

                // Include permissions for action buttons
                'permissions' => [
                    'can_create' => $user->can('payroll-batch.create'),
                    'can_edit' => $user->can('payroll-batch.edit'),
                    'can_submit' => $user->can('payroll-batch.submit'),
                    'can_approve' => $user->can('payroll-batch.approve'),
                    'can_reject' => $user->can('payroll-batch.reject'),
                    'can_mark_paid' => $user->can('payroll-batch.approve'), // Same permission as approve
                ],
            ]);
        }

        return view('admin.hr.payroll.show', compact('payrollBatch'));
    }

    protected function getStatusColor(string $status): string
    {
        return [
            'draft' => 'secondary',
            'submitted' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'paid' => 'primary',
        ][$status] ?? 'secondary';
    }

    public function edit(PayrollBatch $payrollBatch)
    {
        if (!$payrollBatch->canEdit()) {
            return redirect()->route('hr.payroll.show', $payrollBatch)
                ->with('error', 'This batch can no longer be edited.');
        }

        $payrollBatch->load(['items.staff.user', 'items.details']);
        $availableStaff = Staff::active()->withSalaryProfile()
            ->with('user', 'currentSalaryProfile')
            ->get();

        return view('admin.hr.payroll.edit', compact('payrollBatch', 'availableStaff'));
    }

    public function update(Request $request, PayrollBatch $payrollBatch)
    {
        if (!$payrollBatch->canEdit()) {
            return back()->with('error', 'This batch can no longer be edited.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after:pay_period_start',
            'payment_date' => 'nullable|date|after_or_equal:pay_period_end',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $payrollBatch->update($request->only(['name', 'pay_period_start', 'pay_period_end', 'payment_date']));

        return redirect()->route('hr.payroll.edit', $payrollBatch)
            ->with('success', 'Payroll batch updated.');
    }

    public function destroy(PayrollBatch $payrollBatch)
    {
        if (!$payrollBatch->canEdit()) {
            return back()->with('error', 'Cannot delete batch that has been submitted.');
        }

        // Delete items first
        foreach ($payrollBatch->items as $item) {
            $item->details()->delete();
        }
        $payrollBatch->items()->delete();
        $payrollBatch->delete();

        return redirect()->route('hr.payroll.index')
            ->with('success', 'Payroll batch deleted.');
    }

    public function generate(Request $request, PayrollBatch $payrollBatch)
    {
        if (!$payrollBatch->canEdit()) {
            return back()->with('error', 'Cannot generate items for submitted batch.');
        }

        $request->validate([
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'exists:staff,id',
        ]);

        try {
            $count = $this->payrollService->generateBatchItems(
                $payrollBatch,
                $request->staff_ids
            );

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Generated payroll for {$count} staff members.",
                    'count' => $count
                ]);
            }

            return back()->with('success', "Generated payroll for {$count} staff members.");
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    public function submit(Request $request, PayrollBatch $payrollBatch)
    {
        try {
            $this->payrollService->submitBatch($payrollBatch, auth()->user());

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payroll batch submitted for approval.'
                ]);
            }

            return redirect()->route('hr.payroll.show', $payrollBatch)
                ->with('success', 'Payroll batch submitted for approval.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, PayrollBatch $payrollBatch)
    {
        try {
            $this->payrollService->approveBatch(
                $payrollBatch,
                auth()->user(),
                $request->comments
            );

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payroll batch approved. Expense record created.'
                ]);
            }

            return redirect()->route('hr.payroll.show', $payrollBatch)
                ->with('success', 'Payroll batch approved. Expense record created.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, PayrollBatch $payrollBatch)
    {
        // Validate comments/reason
        $validator = Validator::make($request->all(), [
            'comments' => 'required|string|max:500'
        ], [
            'comments.required' => 'Please provide a reason for rejection.'
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator);
        }

        try {
            $this->payrollService->rejectBatch(
                $payrollBatch,
                auth()->user(),
                $request->comments
            );

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payroll batch rejected.'
                ]);
            }

            return redirect()->route('hr.payroll.show', $payrollBatch)
                ->with('success', 'Payroll batch rejected.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Mark an approved batch as paid
     * This is the final step in the payroll workflow
     */
    public function markPaid(Request $request, PayrollBatch $payrollBatch)
    {
        // Only approved batches can be marked as paid
        if ($payrollBatch->status !== PayrollBatch::STATUS_APPROVED) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved batches can be marked as paid.'
                ], 422);
            }
            return back()->with('error', 'Only approved batches can be marked as paid.');
        }

        // Validate payment source
        $validated = $request->validate([
            'comments' => 'nullable|string|max:500',
            'payment_method' => 'required|in:cash,bank_transfer',
            'bank_id' => 'required_if:payment_method,bank_transfer|nullable|exists:banks,id',
        ], [
            'payment_method.required' => 'Please select the payment source (Cash or Bank).',
            'bank_id.required_if' => 'Please select a bank for bank transfer payments.',
        ]);

        try {
            $this->payrollService->markBatchAsPaid(
                $payrollBatch,
                auth()->user(),
                $validated['comments'] ?? null,
                $validated['payment_method'],
                $validated['bank_id'] ?? null
            );

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payroll batch marked as paid. All payments have been recorded.'
                ]);
            }

            return redirect()->route('hr.payroll.show', $payrollBatch)
                ->with('success', 'Payroll batch marked as paid.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    public function revertToDraft(Request $request, PayrollBatch $payrollBatch)
    {
        // Only rejected batches can be reverted to draft
        if (!in_array($payrollBatch->status, [PayrollBatch::STATUS_REJECTED])) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only rejected batches can be reverted to draft.'
                ], 422);
            }
            return back()->with('error', 'Only rejected batches can be reverted to draft.');
        }

        try {
            $payrollBatch->update([
                'status' => PayrollBatch::STATUS_DRAFT,
                'submitted_by' => null,
                'submitted_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payroll batch reverted to draft status.'
                ]);
            }

            return redirect()->route('hr.payroll.edit', $payrollBatch)
                ->with('success', 'Payroll batch reverted to draft status.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    public function payslips(PayrollBatch $payrollBatch)
    {
        $payrollBatch->load(['items.staff.user', 'items.staff.department', 'items.details.payHead']);

        $payslips = $payrollBatch->items->map(function ($item) {
            $data = $this->payrollService->getPayslipData($item);
            $data['item_id'] = $item->id; // Add item ID for print links
            return $data;
        });

        return view('admin.hr.payroll.payslips', compact('payrollBatch', 'payslips'));
    }

    public function export(PayrollBatch $payrollBatch)
    {
        // Export to CSV/Excel
        $payrollBatch->load(['items.staff.user', 'items.staff.department', 'items.details']);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"payroll-{$payrollBatch->batch_number}.csv\"",
        ];

        $callback = function () use ($payrollBatch) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Employee ID', 'Name', 'Department', 'Basic Salary',
                'Additions', 'Deductions', 'Gross Salary', 'Net Salary',
                'Bank Name', 'Account Number', 'Account Name'
            ]);

            foreach ($payrollBatch->items as $item) {
                fputcsv($file, [
                    $item->staff->employee_id ?? $item->staff->id,
                    $item->staff->user->name ?? 'Unknown',
                    $item->staff->department?->name ?? 'N/A',
                    number_format($item->basic_salary, 2),
                    number_format($item->total_additions, 2),
                    number_format($item->total_deductions, 2),
                    number_format($item->gross_salary, 2),
                    number_format($item->net_salary, 2),
                    $item->bank_name ?? 'N/A',
                    $item->bank_account_number ?? 'N/A',
                    $item->bank_account_name ?? 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Print individual payslip with hospital branding
     */
    public function printPayslip(PayrollBatch $payrollBatch, PayrollItem $payrollItem)
    {
        // Verify the payroll item belongs to the batch
        if ($payrollItem->payroll_batch_id !== $payrollBatch->id) {
            abort(404, 'Payslip not found in this batch');
        }

        $payrollItem->load(['staff.user', 'staff.department', 'details.payHead']);

        $payslip = $this->payrollService->getPayslipData($payrollItem);

        return view('admin.hr.payroll.payslip-print', compact('payslip', 'payrollBatch'));
    }
}
