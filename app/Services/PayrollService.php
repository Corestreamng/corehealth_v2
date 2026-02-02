<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\HR\PayrollBatch;
use App\Models\HR\PayrollItem;
use App\Models\HR\PayrollItemDetail;
use App\Models\HR\PayHead;
use App\Models\HR\StaffSalaryProfile;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * HRMS Implementation Plan - Section 6.2
 * Payroll Service - Handles payroll batch processing and expense integration
 */
class PayrollService
{
    /**
     * Check for staff with existing payroll items in a given period
     * Returns list of staff who already have payroll for this period
     */
    public function checkExistingPayrollForPeriod(string $payPeriodStart, string $payPeriodEnd, ?array $staffIds = null): array
    {
        $query = PayrollItem::whereHas('payrollBatch', function ($q) use ($payPeriodStart, $payPeriodEnd) {
            $q->where('pay_period_start', '<=', $payPeriodEnd)
              ->where('pay_period_end', '>=', $payPeriodStart)
              ->whereIn('status', [
                  PayrollBatch::STATUS_DRAFT,
                  PayrollBatch::STATUS_SUBMITTED,
                  PayrollBatch::STATUS_APPROVED,
                  PayrollBatch::STATUS_PAID
              ]);
        })->with(['staff.user', 'staff.department:id,name', 'payrollBatch:id,batch_number,name,status']);

        if ($staffIds) {
            $query->whereIn('staff_id', $staffIds);
        }

        $existingItems = $query->get();

        return $existingItems->map(function ($item) {
            return [
                'staff_id' => $item->staff_id,
                'staff_name' => $item->staff->full_name ?? 'Unknown Staff',
                'employee_id' => $item->staff->employee_id ?? 'N/A',
                'department' => $item->staff->department->name ?? 'N/A',
                'existing_batch' => $item->payrollBatch->name ?? $item->payrollBatch->batch_number,
                'existing_batch_status' => $item->payrollBatch->status,
                'payroll_item_id' => $item->id,
                'net_salary' => $item->net_salary,
            ];
        })->toArray();
    }

    /**
     * Create a new payroll batch
     */
    public function createBatch(array $data, User $creator): PayrollBatch
    {
        // Default payment date to end of pay period if not provided
        $paymentDate = $data['payment_date'] ?? $data['pay_period_end'];

        return PayrollBatch::create([
            'name' => $data['name'],
            'pay_period_start' => $data['pay_period_start'],
            'pay_period_end' => $data['pay_period_end'],
            'work_period_start' => $data['work_period_start'] ?? $data['pay_period_start'],
            'work_period_end' => $data['work_period_end'] ?? $data['pay_period_end'],
            'days_in_month' => $data['days_in_month'] ?? null,
            'days_worked' => $data['days_worked'] ?? null,
            'payment_date' => $paymentDate,
            'status' => PayrollBatch::STATUS_DRAFT,
            'created_by' => $creator->id,
            'total_staff' => 0,
            'total_gross' => 0,
            'total_additions' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
        ]);
    }

    /**
     * Generate payroll items for a batch
     *
     * @param PayrollBatch $batch The batch to generate items for
     * @param array|null $staffIds Optional list of staff IDs to include
     * @param string $duplicateAction How to handle staff with existing payroll: 'skip', 'overwrite', or 'selective'
     * @param array $skipStaffIds Staff IDs to explicitly skip (even if in staffIds)
     * @param array $replaceStaffIds Staff IDs that should replace their existing payroll (used with 'selective' action)
     */
    public function generateBatchItems(
        PayrollBatch $batch,
        ?array $staffIds = null,
        string $duplicateAction = 'skip',
        array $skipStaffIds = [],
        array $replaceStaffIds = []
    ): array {
        if (!$batch->canEdit()) {
            throw new \Exception('Cannot generate items for a batch that is not in draft status.');
        }

        return DB::transaction(function () use ($batch, $staffIds, $duplicateAction, $skipStaffIds, $replaceStaffIds) {
            // Clear existing items in this batch
            $batch->items()->delete();

            // Get staff with salary profiles
            $query = Staff::active()->withSalaryProfile();
            if ($staffIds) {
                $query->whereIn('id', $staffIds);
            }

            // Exclude explicitly skipped staff
            if (!empty($skipStaffIds)) {
                $query->whereNotIn('id', $skipStaffIds);
            }

            $staffMembers = $query->with('currentSalaryProfile.items.payHead')->get();

            // Check for existing payroll items in the same period
            $existingPayrollStaffIds = [];

            if ($duplicateAction === 'selective' && !empty($replaceStaffIds)) {
                // Selective mode: replace specified duplicates, skip the rest
                // First, delete existing items for staff that should be replaced
                $existingItems = PayrollItem::whereIn('staff_id', $replaceStaffIds)
                    ->whereHas('payrollBatch', function ($q) use ($batch) {
                        $q->where('pay_period_start', '<=', $batch->pay_period_end)
                          ->where('pay_period_end', '>=', $batch->pay_period_start)
                          ->where('status', PayrollBatch::STATUS_DRAFT)
                          ->where('id', '!=', $batch->id);
                    })
                    ->get();

                foreach ($existingItems as $item) {
                    $item->details()->delete();
                    $item->delete();
                    $item->payrollBatch->recalculateTotals();
                }

                // Get remaining duplicates (not in replaceStaffIds) to skip
                $existing = $this->checkExistingPayrollForPeriod(
                    $batch->pay_period_start->format('Y-m-d'),
                    $batch->pay_period_end->format('Y-m-d'),
                    $staffMembers->pluck('id')->toArray()
                );
                // Only skip duplicates that weren't selected for replacement
                $existingPayrollStaffIds = collect($existing)
                    ->pluck('staff_id')
                    ->filter(fn($id) => !in_array($id, $replaceStaffIds))
                    ->toArray();
            } elseif ($duplicateAction === 'skip') {
                $existing = $this->checkExistingPayrollForPeriod(
                    $batch->pay_period_start->format('Y-m-d'),
                    $batch->pay_period_end->format('Y-m-d'),
                    $staffMembers->pluck('id')->toArray()
                );
                $existingPayrollStaffIds = collect($existing)->pluck('staff_id')->toArray();
            } elseif ($duplicateAction === 'overwrite') {
                // Delete existing payroll items for these staff in overlapping periods (only from draft batches)
                $existingItems = PayrollItem::whereIn('staff_id', $staffMembers->pluck('id')->toArray())
                    ->whereHas('payrollBatch', function ($q) use ($batch) {
                        $q->where('pay_period_start', '<=', $batch->pay_period_end)
                          ->where('pay_period_end', '>=', $batch->pay_period_start)
                          ->where('status', PayrollBatch::STATUS_DRAFT)
                          ->where('id', '!=', $batch->id);
                    })
                    ->get();

                foreach ($existingItems as $item) {
                    $item->details()->delete();
                    $item->delete();
                    // Recalculate the old batch totals
                    $item->payrollBatch->recalculateTotals();
                }
            }

            $count = 0;
            $skipped = 0;
            $replaced = count($replaceStaffIds);

            foreach ($staffMembers as $staff) {
                // Skip if this staff already has payroll for this period (when in existingPayrollStaffIds)
                if (in_array($staff->id, $existingPayrollStaffIds)) {
                    $skipped++;
                    continue;
                }

                $profile = $staff->currentSalaryProfile;
                if (!$profile) {
                    continue;
                }

                $payrollItem = $this->createPayrollItem($batch, $staff, $profile);
                if ($payrollItem) {
                    $count++;
                }
            }

            // Recalculate batch totals
            $batch->recalculateTotals();

            return [
                'added' => $count,
                'skipped' => $skipped,
                'replaced' => $replaced,
                'total' => $count,
            ];
        });
    }

    /**
     * Create a single payroll item
     */
    protected function createPayrollItem(PayrollBatch $batch, Staff $staff, StaffSalaryProfile $profile): PayrollItem
    {
        $basicSalary = $profile->basic_salary;
        $grossSalary = $basicSalary;
        $totalAdditions = 0;
        $totalDeductions = 0;

        // Get pro-rata info from batch
        $daysInMonth = $batch->days_in_month;
        $daysWorked = $batch->days_worked;
        $isProRata = $daysInMonth && $daysWorked && $daysWorked < $daysInMonth;

        // Create payroll item
        $payrollItem = PayrollItem::create([
            'payroll_batch_id' => $batch->id,
            'staff_id' => $staff->id,
            'salary_profile_id' => $profile->id,
            'days_in_month' => $daysInMonth,
            'days_worked' => $daysWorked,
            'basic_salary' => $basicSalary,
            'full_gross_salary' => 0, // Will be calculated
            'gross_salary' => 0, // Will be calculated
            'total_additions' => 0,
            'total_deductions' => 0,
            'net_salary' => 0,
            'full_net_salary' => 0,
            'bank_name' => $staff->bank_name,
            'bank_account_number' => $staff->bank_account_number,
            'bank_account_name' => $staff->bank_account_name,
        ]);

        // Process additions first
        foreach ($profile->items()->whereHas('payHead', fn($q) => $q->where('type', PayHead::TYPE_ADDITION))->get() as $profileItem) {
            $amount = $profileItem->calculateAmount($basicSalary, $grossSalary);

            PayrollItemDetail::create([
                'payroll_item_id' => $payrollItem->id,
                'pay_head_id' => $profileItem->pay_head_id,
                'type' => PayHead::TYPE_ADDITION,
                'pay_head_name' => $profileItem->payHead->name,
                'amount' => $amount,
            ]);

            $totalAdditions += $amount;
            $grossSalary += $amount;
        }

        // Update gross salary
        $grossSalary = $basicSalary + $totalAdditions;

        // Process deductions
        foreach ($profile->items()->whereHas('payHead', fn($q) => $q->where('type', PayHead::TYPE_DEDUCTION))->get() as $profileItem) {
            $amount = $profileItem->calculateAmount($basicSalary, $grossSalary);

            PayrollItemDetail::create([
                'payroll_item_id' => $payrollItem->id,
                'pay_head_id' => $profileItem->pay_head_id,
                'type' => PayHead::TYPE_DEDUCTION,
                'pay_head_name' => $profileItem->payHead->name,
                'amount' => $amount,
            ]);

            $totalDeductions += $amount;
        }

        // Calculate net salary (full month)
        $fullNetSalary = $grossSalary - $totalDeductions;

        // Apply pro-rata calculation if applicable
        $netSalary = $fullNetSalary;
        if ($isProRata && $daysInMonth > 0) {
            // Pro-rata: (Full Net / Days in Month) × Days Worked
            $netSalary = round(($fullNetSalary / $daysInMonth) * $daysWorked, 2);
        }

        // Update payroll item
        $payrollItem->update([
            'full_gross_salary' => $grossSalary,
            'gross_salary' => $isProRata ? round(($grossSalary / $daysInMonth) * $daysWorked, 2) : $grossSalary,
            'total_additions' => $totalAdditions,
            'total_deductions' => $isProRata ? round(($totalDeductions / $daysInMonth) * $daysWorked, 2) : $totalDeductions,
            'full_net_salary' => $fullNetSalary,
            'net_salary' => $netSalary,
        ]);

        return $payrollItem;
    }

    /**
     * Submit batch for approval
     */
    public function submitBatch(PayrollBatch $batch, User $submitter): PayrollBatch
    {
        if (!$batch->canSubmit()) {
            throw new \Exception('Cannot submit this batch. Ensure it is in draft status and has at least one item.');
        }

        $batch->update([
            'status' => PayrollBatch::STATUS_SUBMITTED,
            'submitted_by' => $submitter->id,
            'submitted_at' => now(),
        ]);

        return $batch->fresh();
    }

    /**
     * Approve batch - approves the payroll for payment
     * Note: Expense is created when marking as paid, not at approval
     */
    public function approveBatch(PayrollBatch $batch, User $approver, ?string $comments = null): PayrollBatch
    {
        if (!$batch->canApprove()) {
            throw new \Exception('Cannot approve this batch. It must be in submitted status.');
        }

        return DB::transaction(function () use ($batch, $approver, $comments) {
            // Update batch - no expense created yet
            $batch->update([
                'status' => PayrollBatch::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_comments' => $comments,
            ]);

            return $batch->fresh();
        });
    }

    /**
     * Reject batch
     */
    public function rejectBatch(PayrollBatch $batch, User $rejector, string $reason): PayrollBatch
    {
        if (!$batch->canApprove()) {
            throw new \Exception('Cannot reject this batch. It must be in submitted status.');
        }

        $batch->update([
            'status' => PayrollBatch::STATUS_REJECTED,
            'rejected_by' => $rejector->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $batch->fresh();
    }

    /**
     * Mark batch as paid - final step in payroll workflow
     * Creates expense with 'pending' status for accountant approval in Expenses module
     *
     * @param PayrollBatch $batch The batch to mark as paid
     * @param User $payer Who is marking it paid
     * @param string|null $comments Optional payment comments
     * @param string|null $paymentMethod 'cash' or 'bank_transfer'
     * @param int|null $bankId Bank ID if payment_method is 'bank_transfer'
     */
    public function markBatchAsPaid(
        PayrollBatch $batch,
        User $payer,
        ?string $comments = null,
        ?string $paymentMethod = 'bank_transfer',
        ?int $bankId = null
    ): PayrollBatch {
        if ($batch->status !== PayrollBatch::STATUS_APPROVED) {
            throw new \Exception('Cannot mark this batch as paid. It must be in approved status.');
        }

        return DB::transaction(function () use ($batch, $payer, $comments, $paymentMethod, $bankId) {
            // Create expense record now (at payment time)
            $expense = $this->createPayrollExpense($batch, $payer, $paymentMethod, $bankId);

            // Update batch status with payment tracking
            $batch->update([
                'status' => PayrollBatch::STATUS_PAID,
                'paid_by' => $payer->id,
                'paid_at' => now(),
                'payment_comments' => $comments,
                'expense_id' => $expense->id,
                'payment_method' => $paymentMethod,
                'bank_id' => $paymentMethod === 'bank_transfer' ? $bankId : null,
            ]);

            return $batch->fresh();
        });
    }

    /**
     * Create expense record for paid payroll
     * Status is 'pending' so accountant can approve in Expenses module
     */
    protected function createPayrollExpense(
        PayrollBatch $batch,
        User $payer,
        ?string $paymentMethod = 'bank_transfer',
        ?int $bankId = null
    ): Expense {
        return Expense::create([
            'title' => "Payroll - {$batch->name}",
            'description' => "Payroll batch {$batch->batch_number} for period {$batch->pay_period_start->format('M d')} - {$batch->pay_period_end->format('M d, Y')}. Total staff: {$batch->total_staff}.",
            'amount' => $batch->total_net,
            'category' => 'salaries',
            'expense_date' => $batch->payment_date ?? now(),
            'payment_method' => $paymentMethod ?? 'bank_transfer',
            'bank_id' => $paymentMethod === 'bank_transfer' ? $bankId : null,
            'status' => 'pending', // Pending for accountant approval
            'payee_type' => 'payroll_batch',
            'payee_name' => "Payroll Batch #{$batch->batch_number}",
            'is_recurring' => false,
            'created_by' => $payer->id,
            // Reference fields
            'reference_type' => 'payroll_batch',
            'reference_id' => $batch->id,
        ]);
    }

    /**
     * Generate payslip data for a staff member in a batch
     */
    public function getPayslipData(PayrollItem $payrollItem): array
    {
        $staff = $payrollItem->staff;
        $batch = $payrollItem->payrollBatch;

        // Determine if pro-rata applies
        $isProRata = $batch->days_worked && $batch->days_in_month && $batch->days_worked < $batch->days_in_month;

        return [
            'payslip_number' => $batch->batch_number . '-' . str_pad($payrollItem->id, 4, '0', STR_PAD_LEFT),
            'pay_period' => $batch->pay_period_start->format('M d') . ' - ' . $batch->pay_period_end->format('M d, Y'),
            'work_period' => $batch->work_period_start && $batch->work_period_end
                ? $batch->work_period_start->format('M d') . ' - ' . $batch->work_period_end->format('M d, Y')
                : null,
            'payment_date' => $batch->payment_date?->format('M d, Y') ?? 'Pending',

            // Pro-rata info
            'is_pro_rata' => $isProRata,
            'days_in_month' => $batch->days_in_month,
            'days_worked' => $batch->days_worked,
            'pro_rata_factor' => $isProRata ? round(($batch->days_worked / $batch->days_in_month) * 100, 1) : 100,

            // Employee info
            'employee_name' => $staff->full_name,
            'employee_id' => $staff->employee_id ?? $staff->id,
            'department' => $staff->department?->name ?? $staff->specialization?->name ?? 'N/A',
            'job_title' => $staff->job_title ?? $staff->specialization?->name ?? 'N/A',

            // Salary breakdown
            'basic_salary' => $payrollItem->basic_salary,
            'additions' => $payrollItem->additions()->get()->map(fn($d) => [
                'name' => $d->pay_head_name,
                'amount' => $d->amount,
            ]),
            'deductions' => $payrollItem->deductions()->get()->map(fn($d) => [
                'name' => $d->pay_head_name,
                'amount' => $d->amount,
            ]),
            'full_gross_salary' => $payrollItem->full_gross_salary,
            'gross_salary' => $payrollItem->gross_salary,
            'total_additions' => $payrollItem->total_additions,
            'total_deductions' => $payrollItem->total_deductions,
            'full_net_salary' => $payrollItem->full_net_salary,
            'net_salary' => $payrollItem->net_salary,

            // Bank details
            'bank_name' => $payrollItem->bank_name,
            'bank_account_number' => $payrollItem->bank_account_number,
            'bank_account_name' => $payrollItem->bank_account_name,
        ];
    }

    /**
     * Get payroll summary statistics
     */
    public function getPayrollStats(?int $year = null): array
    {
        $year = $year ?? now()->year;
        $month = now()->month;

        return [
            'draft' => PayrollBatch::draft()->count(),
            'pending' => PayrollBatch::submitted()->count(),
            'approved' => PayrollBatch::approved()->count(),
            'paid' => PayrollBatch::paid()->count(),
            'rejected' => PayrollBatch::rejected()->count(),
            'month_total' => PayrollBatch::whereYear('pay_period_start', $year)
                ->whereMonth('pay_period_start', $month)
                ->whereIn('status', ['approved', 'paid'])
                ->sum('total_net') ?? 0,
            'total_batches' => PayrollBatch::whereYear('pay_period_start', $year)->count(),
            'pending_approval' => PayrollBatch::submitted()->count(),
            'total_paid' => PayrollBatch::paid()->whereYear('pay_period_start', $year)->sum('total_net'),
            'average_per_batch' => PayrollBatch::paid()->whereYear('pay_period_start', $year)->avg('total_net') ?? 0,
            'total_staff_processed' => PayrollBatch::paid()->whereYear('pay_period_start', $year)->sum('total_staff'),
        ];
    }
}
