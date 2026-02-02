<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\PatientDeposit;
use App\Models\Accounting\PatientDepositApplication;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Patient Deposit Observer
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.9
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 3.2
 *
 * Creates journal entries for patient deposits.
 * Following JE-Centric Architecture - ALL numbers derive from journal_entries.
 *
 * Journal Entry on deposit creation:
 *   DEBIT:  Cash/Bank (1010/1020)
 *   CREDIT: Patient Deposits Liability (2350)
 *
 * Journal Entry on deposit application to bill:
 *   DEBIT:  Patient Deposits Liability (2350)
 *   CREDIT: Accounts Receivable (1200)
 *
 * Journal Entry on refund:
 *   DEBIT:  Patient Deposits Liability (2350)
 *   CREDIT: Cash/Bank (1010/1020)
 */
class PatientDepositObserver
{
    // Account codes
    private const CASH_ACCOUNT = '1010';
    private const BANK_ACCOUNT = '1020';
    private const PATIENT_DEPOSITS_LIABILITY = '2350';
    private const ACCOUNTS_RECEIVABLE = '1200';

    /**
     * Handle the PatientDeposit "created" event.
     * Creates JE: DEBIT Cash/Bank, CREDIT Patient Deposits Liability
     */
    public function created(PatientDeposit $deposit): void
    {
        if ($deposit->journal_entry_id) {
            return; // Already has JE
        }

        try {
            DB::beginTransaction();

            // Determine debit account based on payment method and bank
            $debitAccount = $this->getCashBankAccount($deposit);
            $creditAccount = Account::where('code', self::PATIENT_DEPOSITS_LIABILITY)->first();

            if (!$debitAccount || !$creditAccount) {
                Log::error('PatientDepositObserver: Required accounts not found', [
                    'debit_account' => $debitAccount ? 'found' : 'missing',
                    'credit_code' => self::PATIENT_DEPOSITS_LIABILITY,
                ]);
                DB::rollBack();
                return;
            }

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'entry_date' => $deposit->deposit_date,
                'reference_number' => $deposit->deposit_number,
                'reference_type' => 'patient_deposit',
                'reference_id' => $deposit->id,
                'description' => "Patient deposit: {$deposit->deposit_number} - {$deposit->deposit_type_label}",
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'created_by' => $deposit->received_by,
            ]);

            // DEBIT: Cash/Bank
            $patientName = $deposit->patient?->full_name ?? 'Unknown';
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $debitAccount->id,
                'debit_amount' => $deposit->amount,
                'credit_amount' => 0,
                'description' => "Deposit from patient: {$patientName}",
                'metadata' => [
                    'patient_id' => $deposit->patient_id,
                    'admission_id' => $deposit->admission_id,
                    'deposit_type' => $deposit->deposit_type,
                    'payment_method' => $deposit->payment_method,
                    'payment_reference' => $deposit->payment_reference,
                ],
            ]);

            // CREDIT: Patient Deposits Liability
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $creditAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $deposit->amount,
                'description' => "Patient deposit liability: {$patientName}",
                'metadata' => [
                    'patient_id' => $deposit->patient_id,
                    'admission_id' => $deposit->admission_id,
                    'deposit_type' => $deposit->deposit_type,
                ],
            ]);

            // Link JE to deposit
            $deposit->updateQuietly(['journal_entry_id' => $journalEntry->id]);

            DB::commit();

            Log::info('PatientDepositObserver: JE created for deposit', [
                'deposit_id' => $deposit->id,
                'journal_entry_id' => $journalEntry->id,
                'amount' => $deposit->amount,
                'bank_account_used' => $debitAccount->code,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PatientDepositObserver: Failed to create JE', [
                'deposit_id' => $deposit->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the PatientDeposit "updated" event.
     * Creates refund JE if status changed to refunded.
     */
    public function updated(PatientDeposit $deposit): void
    {
        // Check if being refunded
        if ($deposit->isDirty('status') &&
            $deposit->status === PatientDeposit::STATUS_REFUNDED &&
            $deposit->refunded_amount > 0 &&
            !$deposit->refund_journal_entry_id) {

            $this->createRefundJournalEntry($deposit);
        }
    }

    /**
     * Create refund journal entry.
     * JE: DEBIT Patient Deposits Liability, CREDIT Cash/Bank
     */
    private function createRefundJournalEntry(PatientDeposit $deposit): void
    {
        try {
            DB::beginTransaction();

            // Determine credit account based on original payment method and bank
            $debitAccount = Account::where('code', self::PATIENT_DEPOSITS_LIABILITY)->first();
            $creditAccount = $this->getCashBankAccount($deposit);

            if (!$debitAccount || !$creditAccount) {
                Log::error('PatientDepositObserver: Required accounts not found for refund');
                DB::rollBack();
                return;
            }

            // Create refund journal entry
            $journalEntry = JournalEntry::create([
                'entry_date' => $deposit->refunded_at->toDateString(),
                'reference_number' => "REF-{$deposit->deposit_number}",
                'reference_type' => 'patient_deposit_refund',
                'reference_id' => $deposit->id,
                'description' => "Refund of patient deposit: {$deposit->deposit_number}",
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'created_by' => $deposit->refunded_by,
            ]);

            // DEBIT: Patient Deposits Liability
            $refundPatientName = $deposit->patient?->full_name ?? 'Unknown';
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $debitAccount->id,
                'debit_amount' => $deposit->refunded_amount,
                'credit_amount' => 0,
                'description' => "Refund to patient: {$refundPatientName}",
                'metadata' => [
                    'patient_id' => $deposit->patient_id,
                    'refund_reason' => $deposit->refund_reason,
                ],
            ]);

            // CREDIT: Cash/Bank
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $creditAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $deposit->refunded_amount,
                'description' => "Deposit refund: {$deposit->deposit_number}",
                'metadata' => [
                    'patient_id' => $deposit->patient_id,
                    'original_deposit_id' => $deposit->id,
                ],
            ]);

            // Link refund JE to deposit
            $deposit->updateQuietly(['refund_journal_entry_id' => $journalEntry->id]);

            DB::commit();

            Log::info('PatientDepositObserver: Refund JE created', [
                'deposit_id' => $deposit->id,
                'refund_je_id' => $journalEntry->id,
                'refund_amount' => $deposit->refunded_amount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PatientDepositObserver: Failed to create refund JE', [
                'deposit_id' => $deposit->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get Cash or Bank account based on payment method.
     * Uses specific bank's GL account if bank_id is set.
     */
    protected function getCashBankAccount(PatientDeposit $deposit): ?Account
    {
        // If bank_id is set, use that bank's GL account
        if ($deposit->bank_id) {
            $bank = $deposit->bank;
            if ($bank && $bank->account_id) {
                $account = Account::find($bank->account_id);
                if ($account) {
                    Log::info('PatientDepositObserver: Using bank-specific GL account', [
                        'deposit_id' => $deposit->id,
                        'bank_id' => $bank->id,
                        'bank_name' => $bank->name,
                        'account_id' => $account->id,
                        'account_code' => $account->code,
                    ]);
                    return $account;
                }
            }
        }

        // Fallback to generic cash/bank account codes
        $code = match ($deposit->payment_method) {
            PatientDeposit::METHOD_CASH, 'cash' => self::CASH_ACCOUNT,
            default => self::BANK_ACCOUNT,
        };

        return Account::where('code', $code)->first();
    }
}
