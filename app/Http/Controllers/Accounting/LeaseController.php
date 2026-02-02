<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Lease Controller (IFRS 16 Compliant)
 *
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 7
 *
 * Handles lease management including:
 * - IFRS 16 ROU asset and lease liability calculations
 * - Payment schedules with interest/principal split
 * - ROU asset depreciation
 * - Lease modifications and terminations
 *
 * Access: SUPERADMIN|ADMIN|ACCOUNTS roles
 */
class LeaseController extends Controller
{
    /**
     * Display leases list with dashboard stats.
     */
    public function index()
    {
        $stats = $this->getDashboardStats();

        return view('accounting.leases.index', compact('stats'));
    }

    /**
     * Get dashboard statistics for leases.
     */
    protected function getDashboardStats(): array
    {
        $leases = DB::table('leases')
            ->whereNull('deleted_at')
            ->get();

        $activeLeases = $leases->where('status', 'active');
        $activeCount = $activeLeases->count();

        // Total ROU Asset Value
        $totalRouAsset = $activeLeases->sum('current_rou_asset_value');

        // Total Lease Liability
        $totalLiability = $activeLeases->sum('current_lease_liability');

        // Monthly depreciation (ROU / remaining term)
        $monthlyDepreciation = $activeLeases->sum(function ($lease) {
            $remainingMonths = max(1, Carbon::parse($lease->commencement_date)->diffInMonths(Carbon::parse($lease->end_date)));
            return $lease->initial_rou_asset_value / $remainingMonths;
        });

        // This month's payments due
        $thisMonthStart = Carbon::now()->startOfMonth();
        $thisMonthEnd = Carbon::now()->endOfMonth();

        $paymentsThisMonth = DB::table('lease_payment_schedules')
            ->join('leases', 'lease_payment_schedules.lease_id', '=', 'leases.id')
            ->where('leases.status', 'active')
            ->whereBetween('lease_payment_schedules.due_date', [$thisMonthStart, $thisMonthEnd])
            ->whereNull('lease_payment_schedules.payment_date')
            ->sum('lease_payment_schedules.payment_amount');

        // Overdue payments
        $overduePayments = DB::table('lease_payment_schedules')
            ->join('leases', 'lease_payment_schedules.lease_id', '=', 'leases.id')
            ->where('leases.status', 'active')
            ->where('lease_payment_schedules.due_date', '<', Carbon::now())
            ->whereNull('lease_payment_schedules.payment_date')
            ->sum('lease_payment_schedules.payment_amount');

        // Leases expiring soon (within 90 days)
        $expiringSoon = $activeLeases->filter(function ($lease) {
            return Carbon::parse($lease->end_date)->lte(Carbon::now()->addDays(90));
        })->count();

        // By lease type
        $byType = $activeLeases->groupBy('lease_type')
            ->map(fn($items) => [
                'count' => $items->count(),
                'liability' => $items->sum('current_lease_liability'),
                'rou_asset' => $items->sum('current_rou_asset_value'),
            ])
            ->toArray();

        return [
            'active_count' => $activeCount,
            'total_rou_asset' => $totalRouAsset,
            'total_liability' => $totalLiability,
            'monthly_depreciation' => $monthlyDepreciation,
            'payments_due_this_month' => $paymentsThisMonth,
            'overdue_payments' => $overduePayments,
            'expiring_soon' => $expiringSoon,
            'by_type' => $byType,
        ];
    }

    /**
     * DataTable endpoint for leases.
     */
    public function datatable(Request $request)
    {
        $query = DB::table('leases')
            ->leftJoin('departments', 'leases.department_id', '=', 'departments.id')
            ->leftJoin('suppliers', 'leases.lessor_id', '=', 'suppliers.id')
            ->leftJoin('users', 'leases.created_by', '=', 'users.id')
            ->whereNull('leases.deleted_at')
            ->select([
                'leases.*',
                'departments.name as department_name',
                'suppliers.name as supplier_name',
                'users.name as created_by_name',
            ])
            ->orderBy('leases.created_at', 'desc');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('leases.status', $request->status);
        }

        if ($request->filled('lease_type')) {
            $query->where('leases.lease_type', $request->lease_type);
        }

        if ($request->filled('department_id')) {
            $query->where('leases.department_id', $request->department_id);
        }

        if ($request->filled('lessor')) {
            $query->where(function ($q) use ($request) {
                $q->where('leases.lessor_name', 'like', '%' . $request->lessor . '%')
                  ->orWhere('suppliers.name', 'like', '%' . $request->lessor . '%');
            });
        }

        return DataTables::of($query)
            ->addColumn('commencement_formatted', fn($l) => Carbon::parse($l->commencement_date)->format('M d, Y'))
            ->addColumn('end_date_formatted', fn($l) => Carbon::parse($l->end_date)->format('M d, Y'))
            ->addColumn('monthly_payment_formatted', fn($l) => '₦' . number_format($l->monthly_payment, 2))
            ->addColumn('rou_asset_formatted', fn($l) => '₦' . number_format($l->current_rou_asset_value, 2))
            ->addColumn('liability_formatted', fn($l) => '₦' . number_format($l->current_lease_liability, 2))
            ->addColumn('type_badge', function ($l) {
                $badges = [
                    'operating' => 'badge-secondary',
                    'finance' => 'badge-primary',
                    'short_term' => 'badge-info',
                    'low_value' => 'badge-light',
                ];
                $badge = $badges[$l->lease_type] ?? 'badge-secondary';
                return '<span class="badge ' . $badge . '">' . ucfirst(str_replace('_', ' ', $l->lease_type)) . '</span>';
            })
            ->addColumn('status_badge', function ($l) {
                $badges = [
                    'draft' => 'badge-secondary',
                    'active' => 'badge-success',
                    'expired' => 'badge-dark',
                    'terminated' => 'badge-danger',
                    'purchased' => 'badge-info',
                ];
                $badge = $badges[$l->status] ?? 'badge-secondary';
                return '<span class="badge ' . $badge . '">' . ucfirst($l->status) . '</span>';
            })
            ->addColumn('remaining_term', function ($l) {
                $remaining = Carbon::now()->diffInMonths(Carbon::parse($l->end_date), false);
                return max(0, $remaining) . ' months';
            })
            ->addColumn('actions', function ($l) {
                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . route('accounting.leases.show', $l->id) . '" class="btn btn-outline-info" title="View"><i class="mdi mdi-eye"></i></a>';
                if ($l->status === 'active') {
                    $actions .= '<a href="' . route('accounting.leases.edit', $l->id) . '" class="btn btn-outline-primary" title="Edit"><i class="mdi mdi-pencil"></i></a>';
                    $actions .= '<a href="' . route('accounting.leases.payment', $l->id) . '" class="btn btn-outline-success" title="Record Payment"><i class="mdi mdi-cash"></i></a>';
                }
                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['type_badge', 'status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Show create lease form (multi-step wizard).
     */
    public function create()
    {
        // Get COA accounts for dropdowns
        $rouAssetAccounts = DB::table('accounts')
            ->where('code', 'like', '1%')  // Assets
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $liabilityAccounts = DB::table('accounts')
            ->where('code', 'like', '2%')  // Liabilities
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $expenseAccounts = DB::table('accounts')
            ->where('code', 'like', '5%')  // Expenses
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $departments = DB::table('departments')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $suppliers = DB::table('suppliers')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        return view('accounting.leases.create', compact(
            'rouAssetAccounts',
            'liabilityAccounts',
            'expenseAccounts',
            'departments',
            'suppliers'
        ));
    }

    /**
     * Store a new lease with IFRS 16 calculations.
     */
    public function store(Request $request)
    {
        $request->validate([
            'lease_type' => 'required|in:operating,finance,short_term,low_value',
            'leased_item' => 'required|string|max:255',
            'commencement_date' => 'required|date',
            'end_date' => 'required|date|after:commencement_date',
            'monthly_payment' => 'required|numeric|min:0.01',
            'incremental_borrowing_rate' => 'required|numeric|min:0|max:100',
        ]);

        try {
            DB::beginTransaction();

            // Generate lease number
            $lastLease = DB::table('leases')
                ->orderBy('id', 'desc')
                ->first();
            $nextNumber = $lastLease ? (intval(substr($lastLease->lease_number, 4)) + 1) : 1;
            $leaseNumber = 'LSE-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            // Calculate lease term in months
            $commencementDate = Carbon::parse($request->commencement_date);
            $endDate = Carbon::parse($request->end_date);
            $leaseTermMonths = $commencementDate->diffInMonths($endDate);

            // Calculate IFRS 16 values
            $calculations = $this->calculateIfrs16Values(
                $request->monthly_payment,
                $leaseTermMonths,
                $request->incremental_borrowing_rate,
                $request->annual_rent_increase_rate ?? 0,
                $request->initial_direct_costs ?? 0,
                $request->lease_incentives_received ?? 0
            );

            // Create lease record
            $leaseId = DB::table('leases')->insertGetId([
                'lease_number' => $leaseNumber,
                'lease_type' => $request->lease_type,
                'leased_item' => $request->leased_item,
                'description' => $request->description,
                'lessor_id' => $request->lessor_id,
                'lessor_name' => $request->lessor_name,
                'lessor_contact' => $request->lessor_contact,
                'rou_asset_account_id' => $request->rou_asset_account_id,
                'lease_liability_account_id' => $request->lease_liability_account_id,
                'depreciation_account_id' => $request->depreciation_account_id,
                'interest_account_id' => $request->interest_account_id,
                'commencement_date' => $commencementDate,
                'end_date' => $endDate,
                'lease_term_months' => $leaseTermMonths,
                'monthly_payment' => $request->monthly_payment,
                'annual_rent_increase_rate' => $request->annual_rent_increase_rate ?? 0,
                'incremental_borrowing_rate' => $request->incremental_borrowing_rate,
                'total_lease_payments' => $calculations['total_payments'],
                'initial_rou_asset_value' => $calculations['initial_rou_asset'],
                'initial_lease_liability' => $calculations['initial_liability'],
                'current_rou_asset_value' => $calculations['initial_rou_asset'],
                'accumulated_rou_depreciation' => 0,
                'current_lease_liability' => $calculations['initial_liability'],
                'initial_direct_costs' => $request->initial_direct_costs ?? 0,
                'lease_incentives_received' => $request->lease_incentives_received ?? 0,
                'has_purchase_option' => $request->has_purchase_option ? true : false,
                'purchase_option_amount' => $request->purchase_option_amount,
                'purchase_option_reasonably_certain' => $request->purchase_option_reasonably_certain ? true : false,
                'has_termination_option' => $request->has_termination_option ? true : false,
                'earliest_termination_date' => $request->earliest_termination_date,
                'termination_penalty' => $request->termination_penalty,
                'residual_value_guarantee' => $request->residual_value_guarantee ?? 0,
                'asset_location' => $request->asset_location,
                'department_id' => $request->department_id,
                'status' => 'active',
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Generate payment schedule
            $this->generatePaymentSchedule($leaseId, $calculations['schedule']);

            DB::commit();

            return redirect()->route('accounting.leases.show', $leaseId)
                ->with('success', 'Lease created successfully. Lease Number: ' . $leaseNumber);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Failed to create lease: ' . $e->getMessage());
        }
    }

    /**
     * Calculate IFRS 16 values (ROU Asset, Lease Liability).
     */
    protected function calculateIfrs16Values(
        float $monthlyPayment,
        int $termMonths,
        float $annualRate,
        float $escalationRate = 0,
        float $initialCosts = 0,
        float $incentives = 0
    ): array {
        $monthlyRate = ($annualRate / 100) / 12;
        $schedule = [];
        $totalPayments = 0;
        $openingLiability = 0;

        // First pass: calculate total present value
        $pvPayments = 0;
        $currentPayment = $monthlyPayment;

        for ($i = 1; $i <= $termMonths; $i++) {
            // Apply annual escalation at the start of each year (month 13, 25, etc.)
            if ($escalationRate > 0 && $i > 1 && ($i - 1) % 12 === 0) {
                $currentPayment *= (1 + $escalationRate / 100);
            }

            // Present value factor
            $pvFactor = 1 / pow(1 + $monthlyRate, $i);
            $pvPayments += $currentPayment * $pvFactor;
            $totalPayments += $currentPayment;
        }

        // Initial values
        $initialLiability = $pvPayments;
        $initialRouAsset = $pvPayments + $initialCosts - $incentives;

        // Monthly depreciation (straight-line)
        $monthlyDepreciation = $initialRouAsset / $termMonths;

        // Second pass: build amortization schedule
        $currentPayment = $monthlyPayment;
        $openingLiability = $initialLiability;
        $openingRouValue = $initialRouAsset;

        for ($i = 1; $i <= $termMonths; $i++) {
            // Apply annual escalation
            if ($escalationRate > 0 && $i > 1 && ($i - 1) % 12 === 0) {
                $currentPayment *= (1 + $escalationRate / 100);
            }

            // Interest for the period
            $interestPortion = $openingLiability * $monthlyRate;
            $principalPortion = $currentPayment - $interestPortion;
            $closingLiability = $openingLiability - $principalPortion;

            // ROU depreciation
            $closingRouValue = $openingRouValue - $monthlyDepreciation;

            $schedule[] = [
                'payment_number' => $i,
                'payment_amount' => round($currentPayment, 2),
                'principal_portion' => round($principalPortion, 2),
                'interest_portion' => round($interestPortion, 2),
                'opening_liability' => round($openingLiability, 2),
                'closing_liability' => round(max(0, $closingLiability), 2),
                'rou_depreciation' => round($monthlyDepreciation, 2),
                'opening_rou_value' => round($openingRouValue, 2),
                'closing_rou_value' => round(max(0, $closingRouValue), 2),
            ];

            $openingLiability = max(0, $closingLiability);
            $openingRouValue = max(0, $closingRouValue);
        }

        return [
            'total_payments' => round($totalPayments, 2),
            'initial_liability' => round($initialLiability, 2),
            'initial_rou_asset' => round($initialRouAsset, 2),
            'monthly_depreciation' => round($monthlyDepreciation, 2),
            'total_interest' => round($totalPayments - $initialLiability, 2),
            'schedule' => $schedule,
        ];
    }

    /**
     * Generate payment schedule entries.
     */
    protected function generatePaymentSchedule(int $leaseId, array $schedule): void
    {
        $lease = DB::table('leases')->find($leaseId);
        $paymentDate = Carbon::parse($lease->commencement_date);

        foreach ($schedule as $entry) {
            DB::table('lease_payment_schedules')->insert([
                'lease_id' => $leaseId,
                'payment_number' => $entry['payment_number'],
                'due_date' => $paymentDate->copy()->addMonths($entry['payment_number'] - 1)->format('Y-m-d'),
                'payment_amount' => $entry['payment_amount'],
                'principal_portion' => $entry['principal_portion'],
                'interest_portion' => $entry['interest_portion'],
                'opening_liability' => $entry['opening_liability'],
                'closing_liability' => $entry['closing_liability'],
                'rou_depreciation' => $entry['rou_depreciation'],
                'opening_rou_value' => $entry['opening_rou_value'],
                'closing_rou_value' => $entry['closing_rou_value'],
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Display lease details.
     */
    public function show($id)
    {
        $lease = DB::table('leases')
            ->leftJoin('departments', 'leases.department_id', '=', 'departments.id')
            ->leftJoin('suppliers', 'leases.lessor_id', '=', 'suppliers.id')
            ->leftJoin('accounts as rou_acc', 'leases.rou_asset_account_id', '=', 'rou_acc.id')
            ->leftJoin('accounts as liability_acc', 'leases.lease_liability_account_id', '=', 'liability_acc.id')
            ->leftJoin('accounts as depreciation_acc', 'leases.depreciation_account_id', '=', 'depreciation_acc.id')
            ->leftJoin('accounts as interest_acc', 'leases.interest_account_id', '=', 'interest_acc.id')
            ->leftJoin('users', 'leases.created_by', '=', 'users.id')
            ->select([
                'leases.*',
                'departments.name as department_name',
                'suppliers.name as supplier_name',
                'rou_acc.name as rou_account_name',
                'rou_acc.code as rou_account_code',
                'liability_acc.name as liability_account_name',
                'liability_acc.code as liability_account_code',
                'depreciation_acc.name as depreciation_account_name',
                'depreciation_acc.code as depreciation_account_code',
                'interest_acc.name as interest_account_name',
                'interest_acc.code as interest_account_code',
                'users.name as created_by_name',
            ])
            ->where('leases.id', $id)
            ->first();

        if (!$lease) {
            return redirect()->route('accounting.leases.index')
                ->with('error', 'Lease not found.');
        }

        // Get payment schedule (first 12)
        $schedule = DB::table('lease_payment_schedules')
            ->where('lease_id', $id)
            ->orderBy('payment_number')
            ->limit(12)
            ->get();

        // Payment summary
        $paymentSummary = DB::table('lease_payment_schedules')
            ->where('lease_id', $id)
            ->selectRaw('
                COUNT(*) as total_payments,
                SUM(CASE WHEN payment_date IS NOT NULL THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_date IS NOT NULL THEN actual_payment ELSE 0 END) as total_paid,
                SUM(payment_amount) as total_scheduled,
                SUM(interest_portion) as total_interest,
                SUM(principal_portion) as total_principal
            ')
            ->first();

        // Get next scheduled payment
        $nextPayment = DB::table('lease_payment_schedules')
            ->where('lease_id', $id)
            ->whereNull('payment_date')
            ->orderBy('due_date')
            ->first();

        // Get modifications history
        $modifications = DB::table('lease_modifications')
            ->where('lease_id', $id)
            ->orderBy('modification_date', 'desc')
            ->get();

        return view('accounting.leases.show', compact(
            'lease',
            'schedule',
            'paymentSummary',
            'nextPayment',
            'modifications'
        ));
    }

    /**
     * Show edit form.
     */
    public function edit($id)
    {
        $lease = DB::table('leases')->where('id', $id)->first();

        if (!$lease) {
            return redirect()->route('accounting.leases.index')
                ->with('error', 'Lease not found.');
        }

        if ($lease->status !== 'active') {
            return redirect()->route('accounting.leases.show', $id)
                ->with('error', 'Only active leases can be edited.');
        }

        $departments = DB::table('departments')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('accounting.leases.edit', compact('lease', 'departments'));
    }

    /**
     * Update lease (limited fields).
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'lessor_name' => 'nullable|string|max:255',
            'lessor_contact' => 'nullable|string|max:255',
            'asset_location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::table('leases')
                ->where('id', $id)
                ->update([
                    'lessor_name' => $request->lessor_name,
                    'lessor_contact' => $request->lessor_contact,
                    'asset_location' => $request->asset_location,
                    'department_id' => $request->department_id,
                    'notes' => $request->notes,
                    'updated_at' => now(),
                ]);

            return redirect()->route('accounting.leases.show', $id)
                ->with('success', 'Lease updated successfully.');

        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Failed to update lease: ' . $e->getMessage());
        }
    }

    /**
     * Show payment recording form.
     */
    public function payment($id)
    {
        $lease = DB::table('leases')
            ->leftJoin('suppliers', 'leases.lessor_id', '=', 'suppliers.id')
            ->select(['leases.*', 'suppliers.name as supplier_name'])
            ->where('leases.id', $id)
            ->first();

        if (!$lease) {
            return redirect()->route('accounting.leases.index')
                ->with('error', 'Lease not found.');
        }

        // Get next scheduled payment
        $nextPayment = DB::table('lease_payment_schedules')
            ->where('lease_id', $id)
            ->whereNull('payment_date')
            ->orderBy('due_date')
            ->first();

        // Get bank accounts
        $bankAccounts = DB::table('accounts')
            ->where('code', 'like', '1%')
            ->where('is_bank_account', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('accounting.leases.payment', compact('lease', 'nextPayment', 'bankAccounts'));
    }

    /**
     * Record a lease payment.
     */
    public function recordPayment(Request $request, $id)
    {
        $request->validate([
            'schedule_id' => 'required|exists:lease_payment_schedules,id',
            'payment_date' => 'required|date',
            'actual_payment' => 'required|numeric|min:0.01',
            'bank_account_id' => 'required|exists:accounts,id',
        ]);

        try {
            DB::beginTransaction();

            $schedule = DB::table('lease_payment_schedules')
                ->where('id', $request->schedule_id)
                ->first();

            // Update payment schedule
            DB::table('lease_payment_schedules')
                ->where('id', $request->schedule_id)
                ->update([
                    'payment_date' => $request->payment_date,
                    'actual_payment' => $request->actual_payment,
                    'status' => 'paid',
                    'payment_reference' => $request->payment_reference,
                    'notes' => $request->notes,
                    'updated_at' => now(),
                ]);

            // Update lease current values
            $lease = DB::table('leases')->where('id', $id)->first();

            // Update current lease liability (reduce by principal portion)
            $newLiability = $lease->current_lease_liability - $schedule->principal_portion;

            // Update accumulated depreciation and ROU value
            $newAccumulatedDepreciation = $lease->accumulated_rou_depreciation + $schedule->rou_depreciation;
            $newRouValue = $lease->initial_rou_asset_value - $newAccumulatedDepreciation;

            DB::table('leases')
                ->where('id', $id)
                ->update([
                    'current_lease_liability' => max(0, $newLiability),
                    'accumulated_rou_depreciation' => $newAccumulatedDepreciation,
                    'current_rou_asset_value' => max(0, $newRouValue),
                    'updated_at' => now(),
                ]);

            DB::commit();

            return redirect()->route('accounting.leases.show', $id)
                ->with('success', 'Payment recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Failed to record payment: ' . $e->getMessage());
        }
    }

    /**
     * Run ROU depreciation for all active leases.
     */
    public function runDepreciation(Request $request)
    {
        $request->validate([
            'depreciation_date' => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            $activeLeases = DB::table('leases')
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->get();

            $processedCount = 0;
            $totalDepreciation = 0;

            foreach ($activeLeases as $lease) {
                // Calculate monthly depreciation
                $monthlyDepreciation = $lease->initial_rou_asset_value / $lease->lease_term_months;

                // Check if there's remaining value to depreciate
                if ($lease->current_rou_asset_value > 0) {
                    $depreciation = min($monthlyDepreciation, $lease->current_rou_asset_value);

                    // Update lease values
                    $newAccumulatedDepreciation = $lease->accumulated_rou_depreciation + $depreciation;
                    $newRouValue = $lease->initial_rou_asset_value - $newAccumulatedDepreciation;

                    DB::table('leases')
                        ->where('id', $lease->id)
                        ->update([
                            'accumulated_rou_depreciation' => $newAccumulatedDepreciation,
                            'current_rou_asset_value' => max(0, $newRouValue),
                            'updated_at' => now(),
                        ]);

                    $processedCount++;
                    $totalDepreciation += $depreciation;
                }
            }

            DB::commit();

            return redirect()->route('accounting.leases.index')
                ->with('success', "Depreciation run completed. Processed {$processedCount} leases. Total depreciation: ₦" . number_format($totalDepreciation, 2));

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', 'Failed to run depreciation: ' . $e->getMessage());
        }
    }

    /**
     * Show modification form.
     */
    public function modification($id)
    {
        $lease = DB::table('leases')
            ->leftJoin('suppliers', 'leases.lessor_id', '=', 'suppliers.id')
            ->select(['leases.*', 'suppliers.name as supplier_name'])
            ->where('leases.id', $id)
            ->first();

        if (!$lease) {
            return redirect()->route('accounting.leases.index')
                ->with('error', 'Lease not found.');
        }

        if ($lease->status !== 'active') {
            return redirect()->route('accounting.leases.show', $id)
                ->with('error', 'Only active leases can be modified.');
        }

        return view('accounting.leases.modification', compact('lease'));
    }

    /**
     * Store lease modification.
     */
    public function storeModification(Request $request, $id)
    {
        $request->validate([
            'modification_date' => 'required|date',
            'modification_type' => 'required|in:term_extension,term_reduction,payment_change,scope_change,rate_change',
            'description' => 'required|string',
            'new_monthly_payment' => 'required_if:modification_type,payment_change|nullable|numeric|min:0',
            'new_remaining_term_months' => 'required_if:modification_type,term_extension,term_reduction|nullable|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $lease = DB::table('leases')->where('id', $id)->first();

            // Calculate remaining months
            $remainingMonths = max(1, Carbon::now()->diffInMonths(Carbon::parse($lease->end_date)));

            // Store old values
            $oldValues = [
                'old_lease_liability' => $lease->current_lease_liability,
                'old_rou_asset' => $lease->current_rou_asset_value,
                'old_remaining_term_months' => $remainingMonths,
                'old_monthly_payment' => $lease->monthly_payment,
            ];

            // Calculate new values based on modification type
            $newMonthlyPayment = $request->new_monthly_payment ?? $lease->monthly_payment;
            $newRemainingMonths = $request->new_remaining_term_months ?? $remainingMonths;

            // Recalculate IFRS 16 values
            $calculations = $this->calculateIfrs16Values(
                $newMonthlyPayment,
                $newRemainingMonths,
                $lease->incremental_borrowing_rate,
                $lease->annual_rent_increase_rate
            );

            $adjustmentAmount = $calculations['initial_liability'] - $lease->current_lease_liability;

            // Create modification record
            DB::table('lease_modifications')->insert([
                'lease_id' => $id,
                'modification_date' => $request->modification_date,
                'modification_type' => $request->modification_type,
                'description' => $request->description,
                'old_lease_liability' => $oldValues['old_lease_liability'],
                'old_rou_asset' => $oldValues['old_rou_asset'],
                'old_remaining_term_months' => $oldValues['old_remaining_term_months'],
                'old_monthly_payment' => $oldValues['old_monthly_payment'],
                'new_lease_liability' => $calculations['initial_liability'],
                'new_rou_asset' => $calculations['initial_rou_asset'],
                'new_remaining_term_months' => $newRemainingMonths,
                'new_monthly_payment' => $newMonthlyPayment,
                'adjustment_amount' => $adjustmentAmount,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update lease
            $newEndDate = Carbon::parse($request->modification_date)->addMonths($newRemainingMonths);

            DB::table('leases')
                ->where('id', $id)
                ->update([
                    'monthly_payment' => $newMonthlyPayment,
                    'end_date' => $newEndDate,
                    'lease_term_months' => $newRemainingMonths + ($lease->lease_term_months - $remainingMonths),
                    'current_lease_liability' => $calculations['initial_liability'],
                    'current_rou_asset_value' => $lease->current_rou_asset_value + $adjustmentAmount,
                    'updated_at' => now(),
                ]);

            // Regenerate remaining payment schedule
            DB::table('lease_payment_schedules')
                ->where('lease_id', $id)
                ->whereNull('payment_date')
                ->delete();

            // Generate new schedule for remaining payments
            $this->generatePaymentSchedule($id, $calculations['schedule']);

            DB::commit();

            return redirect()->route('accounting.leases.show', $id)
                ->with('success', 'Lease modification recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Failed to modify lease: ' . $e->getMessage());
        }
    }

    /**
     * Terminate lease early.
     */
    public function terminate(Request $request, $id)
    {
        $request->validate([
            'termination_date' => 'required|date',
            'termination_reason' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $lease = DB::table('leases')->where('id', $id)->first();

            // Update lease status
            DB::table('leases')
                ->where('id', $id)
                ->update([
                    'status' => 'terminated',
                    'notes' => $lease->notes . "\n\nTerminated on " . $request->termination_date . ": " . $request->termination_reason,
                    'updated_at' => now(),
                ]);

            // Cancel remaining scheduled payments
            DB::table('lease_payment_schedules')
                ->where('lease_id', $id)
                ->whereNull('payment_date')
                ->update([
                    'status' => 'scheduled',  // Keep as scheduled but lease is terminated
                    'notes' => 'Cancelled due to early termination',
                    'updated_at' => now(),
                ]);

            DB::commit();

            return redirect()->route('accounting.leases.show', $id)
                ->with('success', 'Lease terminated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', 'Failed to terminate lease: ' . $e->getMessage());
        }
    }

    /**
     * View full payment schedule.
     */
    public function paymentSchedule($id)
    {
        $lease = DB::table('leases')
            ->leftJoin('suppliers', 'leases.lessor_id', '=', 'suppliers.id')
            ->select(['leases.*', 'suppliers.name as supplier_name'])
            ->where('leases.id', $id)
            ->first();

        if (!$lease) {
            return redirect()->route('accounting.leases.index')
                ->with('error', 'Lease not found.');
        }

        $schedule = DB::table('lease_payment_schedules')
            ->where('lease_id', $id)
            ->orderBy('payment_number')
            ->get();

        return view('accounting.leases.schedule', compact('lease', 'schedule'));
    }

    /**
     * IFRS 16 disclosure report.
     */
    public function ifrs16Report()
    {
        $activeLeases = DB::table('leases')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get();

        // Summary statistics
        $summary = [
            'total_rou_assets' => $activeLeases->sum('current_rou_asset_value'),
            'total_accumulated_depreciation' => $activeLeases->sum('accumulated_rou_depreciation'),
            'total_lease_liabilities' => $activeLeases->sum('current_lease_liability'),
            'total_initial_rou' => $activeLeases->sum('initial_rou_asset_value'),
            'total_initial_liability' => $activeLeases->sum('initial_lease_liability'),
        ];

        // Maturity analysis
        $maturityAnalysis = [
            'less_than_1_year' => 0,
            '1_to_5_years' => 0,
            'more_than_5_years' => 0,
        ];

        foreach ($activeLeases as $lease) {
            $remainingMonths = max(0, Carbon::now()->diffInMonths(Carbon::parse($lease->end_date)));

            if ($remainingMonths <= 12) {
                $maturityAnalysis['less_than_1_year'] += $lease->current_lease_liability;
            } elseif ($remainingMonths <= 60) {
                $maturityAnalysis['1_to_5_years'] += $lease->current_lease_liability;
            } else {
                $maturityAnalysis['more_than_5_years'] += $lease->current_lease_liability;
            }
        }

        // By lease type
        $byType = $activeLeases->groupBy('lease_type')
            ->map(fn($items) => [
                'count' => $items->count(),
                'rou_asset' => $items->sum('current_rou_asset_value'),
                'liability' => $items->sum('current_lease_liability'),
            ])
            ->toArray();

        return view('accounting.leases.reports.ifrs16', compact(
            'activeLeases',
            'summary',
            'maturityAnalysis',
            'byType'
        ));
    }

    /**
     * Export leases to PDF.
     */
    public function exportPdf(Request $request)
    {
        $leases = DB::table('leases')
            ->leftJoin('departments', 'leases.department_id', '=', 'departments.id')
            ->whereNull('leases.deleted_at')
            ->when($request->status, fn($q, $status) => $q->where('leases.status', $status))
            ->when($request->type, fn($q, $type) => $q->where('leases.lease_type', $type))
            ->select(['leases.*', 'departments.name as department_name'])
            ->orderBy('leases.lease_number')
            ->get();

        $stats = $this->getDashboardStats();

        $pdf = Pdf::loadView('accounting.leases.export-pdf', compact('leases', 'stats'));
        return $pdf->download('leases-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export leases to Excel.
     */
    public function exportExcel(Request $request)
    {
        $leases = DB::table('leases')
            ->leftJoin('departments', 'leases.department_id', '=', 'departments.id')
            ->whereNull('leases.deleted_at')
            ->when($request->status, fn($q, $status) => $q->where('leases.status', $status))
            ->when($request->type, fn($q, $type) => $q->where('leases.lease_type', $type))
            ->select(['leases.*', 'departments.name as department_name'])
            ->orderBy('leases.lease_number')
            ->get();

        $excelService = app(ExcelExportService::class);
        return $excelService->leases($leases, $request->status);
    }
}
