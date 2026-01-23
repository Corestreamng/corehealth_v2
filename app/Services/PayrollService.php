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
     * Create a new payroll batch
     */
    public function createBatch(array $data, User $creator): PayrollBatch
    {
        return PayrollBatch::create([
            'name' => $data['name'],
            'pay_period_start' => $data['pay_period_start'],
            'pay_period_end' => $data['pay_period_end'],
            'payment_date' => $data['payment_date'] ?? null,
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
     */
    public function generateBatchItems(PayrollBatch $batch, ?array $staffIds = null): int
    {
        if (!$batch->canEdit()) {
            throw new \Exception('Cannot generate items for a batch that is not in draft status.');
        }

        return DB::transaction(function () use ($batch, $staffIds) {
            // Clear existing items
            $batch->items()->delete();

            // Get staff with salary profiles
            $query = Staff::active()->withSalaryProfile();
            if ($staffIds) {
                $query->whereIn('id', $staffIds);
            }
            $staffMembers = $query->with('currentSalaryProfile.items.payHead')->get();

            $count = 0;
            foreach ($staffMembers as $staff) {
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

            return $count;
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

        // Create payroll item
        $payrollItem = PayrollItem::create([
            'payroll_batch_id' => $batch->id,
            'staff_id' => $staff->id,
            'salary_profile_id' => $profile->id,
            'basic_salary' => $basicSalary,
            'gross_salary' => 0, // Will be calculated
            'total_additions' => 0,
            'total_deductions' => 0,
            'net_salary' => 0,
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

        // Calculate net salary
        $netSalary = $grossSalary - $totalDeductions;

        // Update payroll item
        $payrollItem->update([
            'gross_salary' => $grossSalary,
            'total_additions' => $totalAdditions,
            'total_deductions' => $totalDeductions,
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
     * Approve batch and create expense
     */
    public function approveBatch(PayrollBatch $batch, User $approver, ?string $comments = null): PayrollBatch
    {
        if (!$batch->canApprove()) {
            throw new \Exception('Cannot approve this batch. It must be in submitted status.');
        }

        return DB::transaction(function () use ($batch, $approver, $comments) {
            // Create expense record
            $expense = $this->createPayrollExpense($batch, $approver);

            // Update batch
            $batch->update([
                'status' => PayrollBatch::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_comments' => $comments,
                'expense_id' => $expense->id,
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
     * Create expense record for approved payroll
     */
    protected function createPayrollExpense(PayrollBatch $batch, User $approver): Expense
    {
        return Expense::create([
            'title' => "Payroll - {$batch->name}",
            'description' => "Payroll batch {$batch->batch_number} for period {$batch->pay_period_start->format('M d')} - {$batch->pay_period_end->format('M d, Y')}. Total staff: {$batch->total_staff}.",
            'amount' => $batch->total_net,
            'category' => 'salaries',
            'expense_date' => $batch->payment_date ?? now(),
            'payment_method' => 'bank_transfer',
            'status' => 'approved', // Auto-approved since payroll batch was approved
            'payee_type' => 'payroll_batch',
            'payee_name' => "Payroll Batch #{$batch->batch_number}",
            'is_recurring' => false,
            'created_by' => $approver->id,
            'approved_by' => $approver->id,
            'approved_at' => now(),
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

        return [
            'payslip_number' => $batch->batch_number . '-' . str_pad($payrollItem->id, 4, '0', STR_PAD_LEFT),
            'pay_period' => $batch->pay_period_start->format('M d') . ' - ' . $batch->pay_period_end->format('M d, Y'),
            'payment_date' => $batch->payment_date?->format('M d, Y') ?? 'Pending',

            // Employee info
            'employee_name' => $staff->full_name,
            'employee_id' => $staff->employee_id ?? $staff->id,
            'department' => $staff->department ?? $staff->specialization?->name ?? 'N/A',
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
            'gross_salary' => $payrollItem->gross_salary,
            'total_additions' => $payrollItem->total_additions,
            'total_deductions' => $payrollItem->total_deductions,
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

        $batches = PayrollBatch::whereYear('pay_period_start', $year);

        return [
            'total_batches' => $batches->count(),
            'pending_approval' => PayrollBatch::submitted()->count(),
            'total_paid' => PayrollBatch::approved()->whereYear('pay_period_start', $year)->sum('total_net'),
            'average_per_batch' => PayrollBatch::approved()->whereYear('pay_period_start', $year)->avg('total_net') ?? 0,
            'total_staff_processed' => PayrollBatch::approved()->whereYear('pay_period_start', $year)->sum('total_staff'),
        ];
    }
}
