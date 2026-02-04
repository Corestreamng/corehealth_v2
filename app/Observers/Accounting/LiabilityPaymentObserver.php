<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\LiabilityPaymentSchedule;
use App\Models\Accounting\LiabilitySchedule;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Liability Payment Observer
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 4.1A
 *
 * Creates journal entries when a liability payment is recorded.
 * Following JE-Centric Architecture - ALL numbers derive from journal_entries.
 *
 * Journal Entry on payment:
 *   DEBIT:  Liability Account (2000/2300) - Reduce principal balance
 *   DEBIT:  Interest Expense (6300)       - Record interest expense
 *   CREDIT: Bank/Cash Account (1020/1010) - Cash outflow
 *
 * Account Codes Reference:
 * - 1010: Cash on Hand
 * - 1020: Bank Account
 * - 2000: Short-term Loans (Current Liabilities)
 * - 2300: Long-term Loans (Non-Current Liabilities)
 * - 6300: Interest Expense
 */
class LiabilityPaymentObserver
{
    /**
     * Handle the LiabilityPaymentSchedule "updated" event.
     * Creates JE when payment is recorded (payment_date is set).
     */
    public function updated(LiabilityPaymentSchedule $payment): void
    {
        // Only create JE when payment_date is set (payment recorded)
        if (!$payment->isDirty('payment_date') || !$payment->payment_date) {
            return;
        }

        // Don't create duplicate JE
        if ($payment->journal_entry_id) {
            Log::info('LiabilityPaymentObserver: Payment already has JE', [
                'payment_id' => $payment->id,
                'journal_entry_id' => $payment->journal_entry_id,
            ]);
            return;
        }

        $this->createPaymentJournalEntry($payment);
    }

    /**
     * Create journal entry for the payment.
     */
    protected function createPaymentJournalEntry(LiabilityPaymentSchedule $payment): void
    {
        try {
            DB::beginTransaction();

            $liability = $payment->liability;
            if (!$liability) {
                Log::error('LiabilityPaymentObserver: Liability not found', [
                    'payment_id' => $payment->id,
                    'liability_id' => $payment->liability_id,
                ]);
                DB::rollBack();
                return;
            }

            // Get liability account (what we owe)
            $liabilityAccount = Account::find($liability->account_id);
            if (!$liabilityAccount) {
                Log::error('LiabilityPaymentObserver: Liability account not found', [
                    'payment_id' => $payment->id,
                    'account_id' => $liability->account_id,
                ]);
                DB::rollBack();
                return;
            }

            // Get interest expense account
            $interestExpenseAccount = null;
            if ($liability->interest_expense_account_id) {
                $interestExpenseAccount = Account::find($liability->interest_expense_account_id);
            }
            // Fallback to default interest expense account (6300)
            if (!$interestExpenseAccount) {
                $interestExpenseAccount = Account::where('code', '6300')->first();
            }
            if (!$interestExpenseAccount) {
                Log::error('LiabilityPaymentObserver: Interest expense account not found', [
                    'payment_id' => $payment->id,
                ]);
                DB::rollBack();
                return;
            }

            // Get bank/cash account for credit
            $bankAccount = null;
            if ($liability->bank_account_id) {
                $bankAccount = Account::find($liability->bank_account_id);
            }
            if (!$bankAccount) {
                $bankAccount = Account::where('code', '1020')->first(); // Default bank
            }
            if (!$bankAccount) {
                $bankAccount = Account::where('code', '1010')->first(); // Cash fallback
            }
            if (!$bankAccount) {
                Log::error('LiabilityPaymentObserver: Bank/cash account not found', [
                    'payment_id' => $payment->id,
                ]);
                DB::rollBack();
                return;
            }

            // Calculate amounts
            $principalPortion = (float) $payment->principal_portion;
            $interestPortion = (float) $payment->interest_portion;
            $lateFee = (float) ($payment->late_fee ?? 0);
            $actualPayment = (float) ($payment->actual_payment ?? $payment->scheduled_payment);

            // If actual payment differs from scheduled, adjust proportionally
            if ($payment->actual_payment && $payment->actual_payment != $payment->scheduled_payment) {
                $ratio = $payment->actual_payment / $payment->scheduled_payment;
                $principalPortion = round($principalPortion * $ratio, 2);
                $interestPortion = $actualPayment - $principalPortion - $lateFee;
            }

            $totalDebit = $principalPortion + $interestPortion + $lateFee;

            // Liability type for description
            $liabilityTypeLabel = ucfirst(str_replace('_', ' ', $liability->liability_type));

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'accounting_period_id' => AccountingPeriod::current()?->id,
                'entry_date' => $payment->payment_date,
                'reference_number' => "LIA-PAY-{$liability->liability_number}-{$payment->payment_number}",
                'reference_type' => 'liability_payment',
                'reference_id' => $payment->id,
                'description' => "{$liabilityTypeLabel} payment #{$payment->payment_number} to {$liability->creditor_name}",
                'entry_type' => JournalEntry::TYPE_AUTO,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'created_by' => auth()->id() ?? 1,
            ]);

            $lineNumber = 1;

            // Line 1: DEBIT Liability Account (reduce principal)
            if ($principalPortion > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number' => $lineNumber++,
                    'account_id' => $liabilityAccount->id,
                    'debit' => $principalPortion,
                    'credit' => 0,
                    'narration' => "Principal repayment - Payment #{$payment->payment_number}",
                    'metadata' => [
                        'liability_id' => $liability->id,
                        'liability_number' => $liability->liability_number,
                        'payment_id' => $payment->id,
                        'payment_number' => $payment->payment_number,
                        'opening_balance' => $payment->opening_balance,
                        'closing_balance' => $payment->closing_balance,
                    ],
                ]);
            }

            // Line 2: DEBIT Interest Expense
            if ($interestPortion > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number' => $lineNumber++,
                    'account_id' => $interestExpenseAccount->id,
                    'debit' => $interestPortion,
                    'credit' => 0,
                    'narration' => "Interest expense - Payment #{$payment->payment_number}",
                    'metadata' => [
                        'liability_id' => $liability->id,
                        'payment_id' => $payment->id,
                        'interest_rate' => $liability->interest_rate,
                    ],
                ]);
            }

            // Line 3: DEBIT Late Fee (if applicable) - to Interest Expense or separate account
            if ($lateFee > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number' => $lineNumber++,
                    'account_id' => $interestExpenseAccount->id,
                    'debit' => $lateFee,
                    'credit' => 0,
                    'narration' => "Late fee - Payment #{$payment->payment_number}",
                    'metadata' => [
                        'liability_id' => $liability->id,
                        'payment_id' => $payment->id,
                    ],
                ]);
            }

            // Line 4: CREDIT Bank/Cash Account (cash outflow)
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => $lineNumber,
                'account_id' => $bankAccount->id,
                'debit' => 0,
                'credit' => $actualPayment,
                'narration' => "Payment to {$liability->creditor_name}",
                'metadata' => [
                    'liability_id' => $liability->id,
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                ],
            ]);

            // Link JE to payment
            $payment->updateQuietly(['journal_entry_id' => $journalEntry->id]);

            // Update liability balance and next payment date
            $this->updateLiabilityAfterPayment($liability, $payment);

            DB::commit();

            Log::info('LiabilityPaymentObserver: JE created for payment', [
                'payment_id' => $payment->id,
                'liability_id' => $liability->id,
                'journal_entry_id' => $journalEntry->id,
                'principal' => $principalPortion,
                'interest' => $interestPortion,
                'total' => $actualPayment,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('LiabilityPaymentObserver: Failed to create JE', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Update liability record after payment.
     */
    protected function updateLiabilityAfterPayment(LiabilitySchedule $liability, LiabilityPaymentSchedule $payment): void
    {
        // Update current balance
        $newBalance = (float) $liability->current_balance - (float) $payment->principal_portion;
        $liability->current_balance = max(0, $newBalance);

        // Find next scheduled payment
        $nextPayment = LiabilityPaymentSchedule::where('liability_id', $liability->id)
            ->where('status', 'scheduled')
            ->where('due_date', '>', $payment->due_date)
            ->orderBy('due_date')
            ->first();

        if ($nextPayment) {
            $liability->next_payment_date = $nextPayment->due_date;
        } else {
            // No more scheduled payments - check if paid off
            if ($liability->current_balance <= 0) {
                $liability->status = LiabilitySchedule::STATUS_PAID_OFF;
            }
        }

        $liability->saveQuietly();

        // Update current/non-current portions
        $liability->updatePortions();
    }
}
