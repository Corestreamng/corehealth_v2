<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\Lease;
use App\Models\Accounting\LeasePaymentSchedule;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lease Payment Observer
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.13
 *
 * Creates journal entries when a lease payment is recorded.
 * Following JE-Centric Architecture - ALL numbers derive from journal_entries.
 *
 * Journal Entry for IFRS 16 payment:
 *   DEBIT:  Lease Liability (principal)      - 2310
 *   DEBIT:  Interest Expense (interest)      - 6300
 *   CREDIT: Bank/Cash Account (total)        - 1020/1010
 *
 * Journal Entry for exempt lease payment:
 *   DEBIT:  Rent Expense (total)            - 6100
 *   CREDIT: Bank/Cash Account (total)       - 1020/1010
 *
 * Journal Entry for ROU Depreciation (separate):
 *   DEBIT:  Depreciation Expense            - 6260
 *   CREDIT: Accumulated Depreciation (ROU)  - Contra asset
 *
 * Account Codes Reference:
 * - 1010: Cash on Hand
 * - 1020: Bank Account
 * - 2310: Lease Obligations
 * - 6100: Rent Expense
 * - 6260: Depreciation Expense
 * - 6300: Interest Expense
 */
class LeasePaymentObserver
{
    // Default account codes
    private const LEASE_LIABILITY = '2310';
    private const INTEREST_EXPENSE = '6300';
    private const RENT_EXPENSE = '6100';
    private const DEPRECIATION_EXPENSE = '6260';
    private const BANK_ACCOUNT = '1020';
    private const CASH_ACCOUNT = '1010';

    /**
     * Handle the LeasePaymentSchedule "updated" event.
     * Creates JE when payment_date is set (payment recorded).
     */
    public function updated(LeasePaymentSchedule $payment): void
    {
        Log::info('LeasePaymentObserver::updated - Entry', [
            'payment_id' => $payment->id,
            'payment_date' => $payment->payment_date,
            'status' => $payment->status,
            'wasChanged_payment_date' => $payment->wasChanged('payment_date'),
            'wasChanged_status' => $payment->wasChanged('status'),
            'getChanges' => $payment->getChanges(),
        ]);

        // Only process if payment_date was just set (payment recorded)
        // Note: Use wasChanged() in updated event, not isDirty() (which is for before save)
        if (!$payment->wasChanged('payment_date') || !$payment->payment_date) {
            Log::info('LeasePaymentObserver::updated - Skipping (payment_date not changed or null)', [
                'payment_id' => $payment->id,
                'wasChanged' => $payment->wasChanged('payment_date'),
                'payment_date' => $payment->payment_date
            ]);
            return;
        }

        // Skip if already has JE
        if ($payment->journal_entry_id) {
            Log::info('LeasePaymentObserver: Already has JE', [
                'payment_id' => $payment->id,
                'journal_entry_id' => $payment->journal_entry_id
            ]);
            return;
        }

        Log::info('LeasePaymentObserver: Recording payment', [
            'payment_id' => $payment->id,
            'lease_id' => $payment->lease_id
        ]);

        try {
            DB::beginTransaction();

            $lease = $payment->lease;

            if (!$lease) {
                Log::error('LeasePaymentObserver: Lease not found', [
                    'payment_id' => $payment->id,
                    'lease_id' => $payment->lease_id,
                ]);
                DB::rollBack();
                return;
            }

            // Determine if IFRS 16 applies
            $isIfrs16 = !$lease->isExemptFromIfrs16();

            // Get the actual payment amount (use scheduled if not provided)
            $totalPayment = $payment->actual_payment ?? $payment->payment_amount;
            $principalPortion = $payment->principal_portion;
            $interestPortion = $payment->interest_portion;

            // Get bank account - first check session (set by controller), then fall back to defaults
            $bankAccountId = session('lease_payment_bank_account_id');
            $bankAccount = $bankAccountId
                ? Account::find($bankAccountId)
                : (Account::where('code', self::BANK_ACCOUNT)->first() ?? Account::where('code', self::CASH_ACCOUNT)->first());

            if (!$bankAccount) {
                Log::error('LeasePaymentObserver: No bank/cash account found');
                DB::rollBack();
                return;
            }

            if ($isIfrs16) {
                $this->createIfrs16PaymentJE($payment, $lease, $bankAccount, $totalPayment, $principalPortion, $interestPortion);
            } else {
                $this->createExemptLeasePaymentJE($payment, $lease, $bankAccount, $totalPayment);
            }

            // Update lease balances
            if ($isIfrs16) {
                $lease->current_lease_liability = $payment->closing_liability;
                $lease->current_rou_asset_value = $payment->closing_rou_value;
                $lease->accumulated_rou_depreciation += $payment->rou_depreciation;
                $lease->saveQuietly();
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('LeasePaymentObserver: Failed to create JE', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Create JE for IFRS 16 lease payment.
     */
    protected function createIfrs16PaymentJE(
        LeasePaymentSchedule $payment,
        Lease $lease,
        Account $bankAccount,
        float $totalPayment,
        float $principalPortion,
        float $interestPortion
    ): void {
        // Get accounts
        $liabilityAccount = Account::find($lease->lease_liability_account_id)
            ?? Account::where('code', self::LEASE_LIABILITY)->first();

        $interestAccount = Account::find($lease->interest_account_id)
            ?? Account::where('code', self::INTEREST_EXPENSE)->first();

        if (!$liabilityAccount || !$interestAccount) {
            throw new \RuntimeException('Required accounts not found for IFRS 16 payment JE');
        }

        // Create payment journal entry
        $journalEntry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'accounting_period_id' => AccountingPeriod::current()?->id,
            'entry_date' => $payment->payment_date,
            'reference_number' => "LSE-PAY-{$lease->lease_number}-{$payment->payment_number}",
            'reference_type' => 'lease_payment',
            'reference_id' => $payment->id,
            'description' => "Lease payment #{$payment->payment_number} - {$lease->leased_item} ({$lease->lease_number})",
            'entry_type' => JournalEntry::TYPE_AUTO,
            'status' => JournalEntry::STATUS_POSTED,
            'posted_at' => now(),
            'created_by' => auth()->id() ?? 1,
        ]);

        $lineNumber = 1;

        // Line 1: DEBIT Lease Liability (reduce principal)
        if ($principalPortion > 0) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => $lineNumber++,
                'account_id' => $liabilityAccount->id,
                'debit' => $principalPortion,
                'credit' => 0,
                'narration' => "Principal repayment - Payment #{$payment->payment_number}",
                'metadata' => [
                    'lease_id' => $lease->id,
                    'lease_number' => $lease->lease_number,
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'opening_liability' => $payment->opening_liability,
                    'closing_liability' => $payment->closing_liability,
                ],
            ]);
        }

        // Line 2: DEBIT Interest Expense
        if ($interestPortion > 0) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => $lineNumber++,
                'account_id' => $interestAccount->id,
                'debit' => $interestPortion,
                'credit' => 0,
                'narration' => "Interest expense - Payment #{$payment->payment_number}",
                'metadata' => [
                    'lease_id' => $lease->id,
                    'rate' => $lease->incremental_borrowing_rate,
                ],
            ]);
        }

        // Line 3: CREDIT Bank/Cash
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'line_number' => $lineNumber,
            'account_id' => $bankAccount->id,
            'debit' => 0,
            'credit' => $totalPayment,
            'narration' => "Lease payment to {$lease->lessor_name}",
            'metadata' => [
                'lease_id' => $lease->id,
                'payment_reference' => $payment->payment_reference,
            ],
        ]);

        // Link JE to payment
        $payment->updateQuietly(['journal_entry_id' => $journalEntry->id]);

        Log::info('LeasePaymentObserver: IFRS 16 payment JE created', [
            'payment_id' => $payment->id,
            'journal_entry_id' => $journalEntry->id,
            'principal' => $principalPortion,
            'interest' => $interestPortion,
            'total' => $totalPayment,
        ]);
    }

    /**
     * Create JE for exempt (short-term/low-value) lease payment.
     * Entire payment is rent expense.
     */
    protected function createExemptLeasePaymentJE(
        LeasePaymentSchedule $payment,
        Lease $lease,
        Account $bankAccount,
        float $totalPayment
    ): void {
        // Get rent expense account
        $rentExpenseAccount = Account::where('code', self::RENT_EXPENSE)->first();

        if (!$rentExpenseAccount) {
            throw new \RuntimeException('Rent expense account (6100) not found');
        }

        // Create payment journal entry
        $journalEntry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'accounting_period_id' => AccountingPeriod::current()?->id,
            'entry_date' => $payment->payment_date,
            'reference_number' => "LSE-PAY-{$lease->lease_number}-{$payment->payment_number}",
            'reference_type' => 'lease_payment',
            'reference_id' => $payment->id,
            'description' => "Lease payment #{$payment->payment_number} (exempt) - {$lease->leased_item} ({$lease->lease_number})",
            'entry_type' => JournalEntry::TYPE_AUTO,
            'status' => JournalEntry::STATUS_POSTED,
            'posted_at' => now(),
            'created_by' => auth()->id() ?? 1,
        ]);

        // Line 1: DEBIT Rent Expense
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'line_number' => 1,
            'account_id' => $rentExpenseAccount->id,
            'debit' => $totalPayment,
            'credit' => 0,
            'narration' => "Rent expense - {$lease->leased_item}",
            'metadata' => [
                'lease_id' => $lease->id,
                'lease_type' => $lease->lease_type,
                'exempt_reason' => $lease->lease_type === Lease::TYPE_SHORT_TERM ? 'Short-term (≤12 months)' : 'Low-value asset',
            ],
        ]);

        // Line 2: CREDIT Bank/Cash
        JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'line_number' => 2,
            'account_id' => $bankAccount->id,
            'debit' => 0,
            'credit' => $totalPayment,
            'narration' => "Payment to {$lease->lessor_name}",
            'metadata' => [
                'lease_id' => $lease->id,
                'payment_reference' => $payment->payment_reference,
            ],
        ]);

        // Link JE to payment
        $payment->updateQuietly(['journal_entry_id' => $journalEntry->id]);

        Log::info('LeasePaymentObserver: Exempt lease payment JE created', [
            'payment_id' => $payment->id,
            'journal_entry_id' => $journalEntry->id,
            'amount' => $totalPayment,
            'lease_type' => $lease->lease_type,
        ]);
    }

    /**
     * Process payment directly (for trigger scripts, bypasses isDirty check).
     * Call this method when you need to create JE for an existing paid payment.
     */
    public function processPayment(LeasePaymentSchedule $payment): void
    {
        // Skip if no payment date
        if (!$payment->payment_date) {
            Log::info('LeasePaymentObserver::processPayment: No payment date', [
                'payment_id' => $payment->id,
            ]);
            return;
        }

        // Skip if already has JE
        if ($payment->journal_entry_id) {
            Log::info('LeasePaymentObserver::processPayment: Already has JE', [
                'payment_id' => $payment->id,
                'journal_entry_id' => $payment->journal_entry_id
            ]);
            return;
        }

        Log::info('LeasePaymentObserver::processPayment: Processing', [
            'payment_id' => $payment->id,
            'lease_id' => $payment->lease_id
        ]);

        try {
            DB::beginTransaction();

            $lease = $payment->lease;

            if (!$lease) {
                Log::error('LeasePaymentObserver::processPayment: Lease not found', [
                    'payment_id' => $payment->id,
                    'lease_id' => $payment->lease_id,
                ]);
                DB::rollBack();
                return;
            }

            // Determine if IFRS 16 applies
            $isIfrs16 = !$lease->isExemptFromIfrs16();

            // Get the actual payment amount
            $totalPayment = $payment->actual_payment ?? $payment->payment_amount;
            $principalPortion = $payment->principal_portion;
            $interestPortion = $payment->interest_portion;

            // Get bank account - first check session, then fall back to defaults
            $bankAccountId = session('lease_payment_bank_account_id');
            $bankAccount = $bankAccountId
                ? Account::find($bankAccountId)
                : (Account::where('code', self::BANK_ACCOUNT)->first() ?? Account::where('code', self::CASH_ACCOUNT)->first());

            if (!$bankAccount) {
                Log::error('LeasePaymentObserver::processPayment: No bank/cash account found');
                DB::rollBack();
                return;
            }

            if ($isIfrs16) {
                $this->createIfrs16PaymentJE($payment, $lease, $bankAccount, $totalPayment, $principalPortion, $interestPortion);
            } else {
                $this->createExemptLeasePaymentJE($payment, $lease, $bankAccount, $totalPayment);
            }

            // Update lease balances
            if ($isIfrs16) {
                $lease->current_lease_liability = $payment->closing_liability;
                $lease->current_rou_asset_value = $payment->closing_rou_value;
                $lease->accumulated_rou_depreciation += $payment->rou_depreciation;
                $lease->saveQuietly();
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('LeasePaymentObserver::processPayment: Failed to create JE', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
