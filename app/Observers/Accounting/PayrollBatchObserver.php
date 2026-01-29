<?php

namespace App\Observers\Accounting;

use App\Models\HR\PayrollBatch;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Payroll Batch Observer
 *
 * Reference: Accounting System Plan §6.2 - Automated Journal Entries
 *
 * Creates journal entries when payroll is paid.
 *
 * DEBIT:  Salaries Expense
 * CREDIT: Cash / Bank
 */
class PayrollBatchObserver
{
    /**
     * Handle the PayrollBatch "updated" event.
     */
    public function updated(PayrollBatch $batch): void
    {
        // Create journal entry when payroll is paid
        if ($batch->isDirty('status') && $batch->status === PayrollBatch::STATUS_PAID) {
            try {
                $this->createPayrollJournalEntry($batch);
            } catch (\Exception $e) {
                Log::error('Failed to create journal entry for payroll', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Create journal entry for payroll payment.
     */
    protected function createPayrollJournalEntry(PayrollBatch $batch): void
    {
        $accountingService = App::make(AccountingService::class);

        $salaryAccount = Account::where('code', '6040')->first(); // Salaries & Wages
        $bankAccount = Account::where('code', '1020')->first(); // Bank Account

        if (!$salaryAccount || !$bankAccount) {
            Log::warning('Payroll journal entry skipped - accounts not configured', [
                'batch_id' => $batch->id
            ]);
            return;
        }

        $description = "Payroll Payment: {$batch->name} ({$batch->batch_number})";
        $description .= " | Period: {$batch->pay_period_start->format('M d')} - {$batch->pay_period_end->format('M d, Y')}";

        $lines = [
            [
                'account_id' => $salaryAccount->id,
                'debit_amount' => $batch->total_net,
                'credit_amount' => 0,
                'description' => "Gross salary: {$batch->total_gross}, Deductions: {$batch->total_deductions}"
            ],
            [
                'account_id' => $bankAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $batch->total_net,
                'description' => 'Net payment to staff'
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            PayrollBatch::class,
            $batch->id,
            $description,
            $lines
        );

        // Update batch with journal entry reference
        $batch->journal_entry_id = $entry->id;
        $batch->saveQuietly();
    }
}
