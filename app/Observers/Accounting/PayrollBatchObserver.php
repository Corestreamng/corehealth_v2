<?php

namespace App\Observers\Accounting;

use App\Models\HR\PayrollBatch;
use App\Models\HR\PayHead;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payroll Batch Observer (UPDATED for Accrual Accounting + Deduction Liabilities)
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.3.1
 *
 * TWO-STAGE ACCRUAL ENTRIES:
 *
 * Stage 1 - When APPROVED:
 * DEBIT:  Salaries & Wages Expense (6040) - total_gross
 * CREDIT: Salaries Payable (2050) - total_net (what staff will receive)
 * CREDIT: PAYE Payable (2060) - if payhead has liability_account_id
 * CREDIT: Pension Payable (2040) - if payhead has liability_account_id
 * CREDIT: [Other deduction liabilities] - dynamically based on payhead settings
 *
 * Stage 2 - When PAID:
 * DEBIT:  Salaries Payable (2050) - total_net
 * CREDIT: Bank Account (1020 or selected) - total_net
 *
 * METADATA CAPTURED:
 * - department_id: Associated department (if departmental payroll)
 * - category: 'payroll_expense' or 'payroll_payment'
 */
class PayrollBatchObserver
{
    /**
     * Handle the PayrollBatch "updated" event.
     */
    public function updated(PayrollBatch $batch): void
    {
        if (!$batch->isDirty('status')) {
            return;
        }

        try {
            // Stage 1: Approved - Recognize expense and liability
            if ($batch->status === PayrollBatch::STATUS_APPROVED) {
                $this->createExpenseRecognitionEntry($batch);
            }

            // Stage 2: Paid - Clear liability and reduce bank
            if ($batch->status === PayrollBatch::STATUS_PAID) {
                $this->createPaymentEntry($batch);
            }
        } catch (\Exception $e) {
            Log::error('PayrollBatchObserver: Failed to create journal entry', [
                'batch_id' => $batch->id,
                'status' => $batch->status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Stage 1: Recognize salary expense and create liabilities
     * - Posts gross salary to expense account
     * - Posts net salary to Salaries Payable
     * - Posts deductions to their respective liability accounts (if configured)
     */
    protected function createExpenseRecognitionEntry(PayrollBatch $batch): void
    {
        $accountingService = App::make(AccountingService::class);

        $salaryExpense = Account::where('code', '6040')->first(); // Salaries & Wages
        $salaryPayable = Account::where('code', '2050')->first(); // Salaries Payable

        if (!$salaryExpense || !$salaryPayable) {
            Log::warning('PayrollBatchObserver: Expense entry skipped - accounts not configured', [
                'batch_id' => $batch->id,
                'salary_expense_found' => !is_null($salaryExpense),
                'salary_payable_found' => !is_null($salaryPayable),
            ]);
            return;
        }

        // Format period dates safely
        $periodStart = $batch->pay_period_start ? $batch->pay_period_start->format('M d') : 'N/A';
        $periodEnd = $batch->pay_period_end ? $batch->pay_period_end->format('M d, Y') : 'N/A';

        // Aggregate deductions by pay_head_id across all payroll items
        $deductionsByPayHead = $this->aggregateDeductionsByPayHead($batch);

        // Start building journal entry lines
        $lines = [];

        // DEBIT: Salary Expense (total gross)
        $lines[] = [
            'account_id' => $salaryExpense->id,
            'debit_amount' => $batch->total_gross,
            'credit_amount' => 0,
            'description' => "Salary expense: {$batch->name} ({$batch->total_staff} staff)",
            'department_id' => $batch->department_id ?? null,
            'category' => 'payroll_expense',
        ];

        // CREDIT: Salaries Payable (net amount - what staff will receive)
        $lines[] = [
            'account_id' => $salaryPayable->id,
            'debit_amount' => 0,
            'credit_amount' => $batch->total_net,
            'description' => "Net salary payable: {$periodStart} - {$periodEnd}",
            'department_id' => $batch->department_id ?? null,
            'category' => 'payroll_expense',
        ];

        // CREDIT: Individual deduction liabilities (for payheads with liability_account_id)
        $deductionsWithAccounts = 0;
        $deductionsWithoutAccounts = 0;

        foreach ($deductionsByPayHead as $deduction) {
            if ($deduction['liability_account_id']) {
                $lines[] = [
                    'account_id' => $deduction['liability_account_id'],
                    'debit_amount' => 0,
                    'credit_amount' => $deduction['total_amount'],
                    'description' => "{$deduction['pay_head_name']} deduction: {$batch->name}",
                    'department_id' => $batch->department_id ?? null,
                    'category' => 'payroll_deduction',
                ];
                $deductionsWithAccounts += $deduction['total_amount'];
            } else {
                $deductionsWithoutAccounts += $deduction['total_amount'];
            }
        }

        // If there are deductions without linked accounts, they remain in Salaries Payable
        // This is already handled by posting total_net to Salaries Payable
        // The difference (gross - net) should equal total deductions

        // Verify the journal entry balances
        $totalDebits = $batch->total_gross;
        $totalCredits = $batch->total_net + $deductionsWithAccounts;
        $expectedCredits = $batch->total_gross;

        if (abs($totalCredits - $expectedCredits) > 0.01) {
            // There's an imbalance - some deductions don't have linked accounts
            // Add the difference to a general deductions payable or log warning
            Log::info('PayrollBatchObserver: Some deductions have no linked liability accounts', [
                'batch_id' => $batch->id,
                'deductions_with_accounts' => $deductionsWithAccounts,
                'deductions_without_accounts' => $deductionsWithoutAccounts,
                'total_deductions' => $batch->total_deductions,
            ]);
        }

        $description = "Payroll Expense Recognition: {$batch->name} | Period: {$periodStart} - {$periodEnd} | Staff: {$batch->total_staff} | Gross: " . number_format($batch->total_gross, 2) . " | Net: " . number_format($batch->total_net, 2);

        $accountingService->createAndPostAutomatedEntry(
            PayrollBatch::class,
            $batch->id,
            $description,
            $lines,
            $batch->approved_at ? $batch->approved_at->toDateString() : null
        );

        Log::info('PayrollBatchObserver: Expense recognition entry created', [
            'batch_id' => $batch->id,
            'gross_amount' => $batch->total_gross,
            'net_amount' => $batch->total_net,
            'deductions_with_accounts' => $deductionsWithAccounts,
            'deduction_payheads_count' => count($deductionsByPayHead),
        ]);
    }

    /**
     * Aggregate all deductions by pay_head_id for this batch.
     * Returns array of ['pay_head_id' => ..., 'pay_head_name' => ..., 'total_amount' => ..., 'liability_account_id' => ...]
     */
    protected function aggregateDeductionsByPayHead(PayrollBatch $batch): array
    {
        // Query to get sum of deductions grouped by pay_head_id
        $deductions = DB::table('payroll_item_details')
            ->join('payroll_items', 'payroll_items.id', '=', 'payroll_item_details.payroll_item_id')
            ->join('pay_heads', 'pay_heads.id', '=', 'payroll_item_details.pay_head_id')
            ->where('payroll_items.payroll_batch_id', $batch->id)
            ->where('payroll_item_details.type', PayHead::TYPE_DEDUCTION)
            ->select(
                'payroll_item_details.pay_head_id',
                'pay_heads.name as pay_head_name',
                'pay_heads.liability_account_id',
                DB::raw('SUM(payroll_item_details.amount) as total_amount')
            )
            ->groupBy('payroll_item_details.pay_head_id', 'pay_heads.name', 'pay_heads.liability_account_id')
            ->get();

        return $deductions->map(function ($d) {
            return [
                'pay_head_id' => $d->pay_head_id,
                'pay_head_name' => $d->pay_head_name,
                'liability_account_id' => $d->liability_account_id,
                'total_amount' => (float) $d->total_amount,
            ];
        })->toArray();
    }

    /**
     * Stage 2: Clear liability and pay from bank
     */
    protected function createPaymentEntry(PayrollBatch $batch): void
    {
        $accountingService = App::make(AccountingService::class);

        $salaryPayable = Account::where('code', '2050')->first(); // Salaries Payable
        $bankAccount = $this->getBankAccount($batch);

        if (!$salaryPayable || !$bankAccount) {
            Log::warning('PayrollBatchObserver: Payment entry skipped - accounts not configured', [
                'batch_id' => $batch->id,
                'salary_payable_found' => !is_null($salaryPayable),
                'bank_account_found' => !is_null($bankAccount),
            ]);
            return;
        }

        $lines = [
            [
                'account_id' => $salaryPayable->id,
                'debit_amount' => $batch->total_net,
                'credit_amount' => 0,
                'description' => "Salary liability cleared: {$batch->name}",
                // METADATA
                'department_id' => $batch->department_id ?? null,
                'category' => 'payroll_payment',
            ],
            [
                'account_id' => $bankAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $batch->total_net,
                'description' => "Net salary payment to {$batch->total_staff} staff via bank",
                // METADATA
                'department_id' => $batch->department_id ?? null,
                'category' => 'payroll_payment',
            ]
        ];

        // Use unique reference to avoid duplicate entry error (append -payment suffix)
        $entry = $accountingService->createAndPostAutomatedEntry(
            PayrollBatch::class . ':payment',
            $batch->id,
            "Payroll Payment: {$batch->name} | Net: " . number_format($batch->total_net, 2) . " | Staff: {$batch->total_staff}",
            $lines,
            $batch->paid_at ? $batch->paid_at->toDateString() : null
        );

        // Link journal entry to batch
        $batch->journal_entry_id = $entry->id;
        $batch->saveQuietly();

        Log::info('PayrollBatchObserver: Payment entry created', [
            'batch_id' => $batch->id,
            'journal_entry_id' => $entry->id,
            'net_amount' => $batch->total_net,
        ]);
    }

    /**
     * Get the bank account for this payment.
     */
    protected function getBankAccount(PayrollBatch $batch): ?Account
    {
        // If specific account_id set, use it
        if ($batch->account_id) {
            return Account::find($batch->account_id);
        }

        // If bank_id set, find its linked account
        if ($batch->bank_id) {
            $bank = $batch->bank;
            if ($bank && $bank->account_id) {
                return Account::find($bank->account_id);
            }
        }

        // Map by payment method
        $code = match ($batch->payment_method ?? 'bank_transfer') {
            'cash' => '1010',
            'bank_transfer' => '1020',
            default => '1020'
        };

        return Account::where('code', $code)->first();
    }
}
