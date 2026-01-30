<?php

namespace App\Observers\Accounting;

use App\Models\HR\PayrollBatch;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Payroll Batch Observer (UPDATED for Accrual Accounting + Metadata)
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.3.1
 *
 * TWO-STAGE ACCRUAL ENTRIES:
 *
 * Stage 1 - When APPROVED:
 * DEBIT:  Salaries & Wages Expense (6040)
 * CREDIT: Salaries Payable (2050)
 *
 * Stage 2 - When PAID:
 * DEBIT:  Salaries Payable (2050)
 * CREDIT: Bank Account (1020 or selected)
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
     * Stage 1: Recognize salary expense and create liability
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

        $lines = [
            [
                'account_id' => $salaryExpense->id,
                'debit_amount' => $batch->total_gross,
                'credit_amount' => 0,
                'description' => "Salary expense: {$batch->name} ({$batch->total_staff} staff)",
                // METADATA
                'department_id' => $batch->department_id ?? null,
                'category' => 'payroll_expense',
            ],
            [
                'account_id' => $salaryPayable->id,
                'debit_amount' => 0,
                'credit_amount' => $batch->total_gross,
                'description' => "Salary liability: {$periodStart} - {$periodEnd}",
                // METADATA
                'department_id' => $batch->department_id ?? null,
                'category' => 'payroll_expense',
            ]
        ];

        $description = "Payroll Expense Recognition: {$batch->name} | Period: {$periodStart} - {$periodEnd} | Staff: {$batch->total_staff} | Gross: " . number_format($batch->total_gross, 2);

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
        ]);
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
