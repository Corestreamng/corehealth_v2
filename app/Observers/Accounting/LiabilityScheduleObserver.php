<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\LiabilitySchedule;
use App\Models\Accounting\LiabilityPaymentSchedule;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Liability Schedule Observer
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 4.1A
 *
 * Creates journal entries when a new liability is created (loan received).
 * Following JE-Centric Architecture - ALL numbers derive from journal_entries.
 *
 * Journal Entry on liability creation (loan received):
 *   DEBIT:  Bank/Cash Account (1020/1010) - Money received
 *   CREDIT: Liability Account (2000/2300) - Loan payable
 *
 * Account Codes Reference:
 * - 1010: Cash on Hand
 * - 1020: Bank Account
 * - 2000: Short-term Loans (Current Liabilities)
 * - 2300: Long-term Loans (Non-Current Liabilities)
 * - 6300: Interest Expense
 */
class LiabilityScheduleObserver
{
    /**
     * Handle the LiabilitySchedule "created" event.
     * Creates JE: DEBIT Bank, CREDIT Liability Account
     */
    public function created(LiabilitySchedule $liability): void
    {
        Log::info('LiabilityScheduleObserver: created() called', ['liability_id' => $liability->id]);

        if ($liability->journal_entry_id) {
            Log::info('LiabilityScheduleObserver: Already has JE', ['journal_entry_id' => $liability->journal_entry_id]);
            return;
        }

        // Only create JE for active liabilities
        if ($liability->status !== LiabilitySchedule::STATUS_ACTIVE) {
            Log::info('LiabilityScheduleObserver: Skipping JE for non-active liability', [
                'liability_id' => $liability->id,
                'status' => $liability->status
            ]);
            return;
        }

        try {
            DB::beginTransaction();

            // Get liability account (from the liability record)
            $liabilityAccount = Account::find($liability->account_id);
            if (!$liabilityAccount) {
                Log::error('LiabilityScheduleObserver: Liability account not found', [
                    'liability_id' => $liability->id,
                    'account_id' => $liability->account_id,
                ]);
                DB::rollBack();
                return;
            }

            // Determine bank account (where loan proceeds were deposited)
            $bankAccount = null;

            if ($liability->bank_account_id) {
                $bankAccount = Account::find($liability->bank_account_id);
            }

            // Fallback to default bank account (code 1020)
            if (!$bankAccount) {
                $bankAccount = Account::where('code', '1020')->first();
            }

            // Last resort: use cash account (code 1010)
            if (!$bankAccount) {
                $bankAccount = Account::where('code', '1010')->first();
            }

            if (!$bankAccount) {
                Log::error('LiabilityScheduleObserver: No bank/cash account found', [
                    'liability_id' => $liability->id,
                ]);
                DB::rollBack();
                return;
            }

            // Determine liability type description
            $liabilityTypeLabel = ucfirst(str_replace('_', ' ', $liability->liability_type));

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'accounting_period_id' => AccountingPeriod::current()?->id,
                'entry_date' => $liability->start_date,
                'reference_number' => "LIA-{$liability->liability_number}",
                'reference_type' => 'liability_schedule',
                'reference_id' => $liability->id,
                'description' => "{$liabilityTypeLabel} received from {$liability->creditor_name}: {$liability->liability_number}",
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'created_by' => $liability->created_by ?? auth()->id() ?? 1,
            ]);

            // Line 1: DEBIT Bank/Cash Account (money received)
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => 1,
                'account_id' => $bankAccount->id,
                'debit' => $liability->principal_amount,
                'credit' => 0,
                'narration' => "{$liabilityTypeLabel} proceeds received",
                'metadata' => [
                    'liability_id' => $liability->id,
                    'liability_number' => $liability->liability_number,
                    'creditor' => $liability->creditor_name,
                    'reference_number' => $liability->reference_number,
                ],
            ]);

            // Line 2: CREDIT Liability Account (loan payable)
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => 2,
                'account_id' => $liabilityAccount->id,
                'debit' => 0,
                'credit' => $liability->principal_amount,
                'narration' => "{$liabilityTypeLabel} payable to {$liability->creditor_name}",
                'metadata' => [
                    'liability_id' => $liability->id,
                    'liability_number' => $liability->liability_number,
                    'interest_rate' => $liability->interest_rate,
                    'term_months' => $liability->term_months,
                    'maturity_date' => $liability->maturity_date,
                ],
            ]);

            // Link JE to liability
            $liability->updateQuietly(['journal_entry_id' => $journalEntry->id]);

            DB::commit();

            Log::info('LiabilityScheduleObserver: JE created for liability', [
                'liability_id' => $liability->id,
                'liability_number' => $liability->liability_number,
                'journal_entry_id' => $journalEntry->id,
                'amount' => $liability->principal_amount,
                'debit_account' => $bankAccount->code,
                'credit_account' => $liabilityAccount->code,
            ]);

            // Generate payment schedule after JE is created
            $this->generatePaymentSchedule($liability);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('LiabilityScheduleObserver: Failed to create JE', [
                'liability_id' => $liability->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Generate amortization schedule for the liability
     */
    protected function generatePaymentSchedule(LiabilitySchedule $liability): void
    {
        // Don't regenerate if payments already exist
        if ($liability->paymentSchedules()->count() > 0) {
            return;
        }

        $principal = (float) $liability->principal_amount;
        $annualRate = (float) $liability->interest_rate / 100;
        $termMonths = (int) $liability->term_months;
        $frequency = $liability->payment_frequency;
        $startDate = Carbon::parse($liability->start_date);
        $interestType = $liability->interest_type;

        // Calculate periods per year based on frequency
        $periodsPerYear = match($frequency) {
            'weekly' => 52,
            'bi_weekly' => 26,
            'monthly' => 12,
            'quarterly' => 4,
            'semi_annually' => 2,
            'annually' => 1,
            'at_maturity' => 1,
            default => 12,
        };

        // Calculate total number of payments
        $totalPayments = match($frequency) {
            'weekly' => ceil($termMonths * 52 / 12),
            'bi_weekly' => ceil($termMonths * 26 / 12),
            'monthly' => $termMonths,
            'quarterly' => ceil($termMonths / 3),
            'semi_annually' => ceil($termMonths / 6),
            'annually' => ceil($termMonths / 12),
            'at_maturity' => 1,
            default => $termMonths,
        };

        // Calculate regular payment using amortization formula
        $periodicRate = $annualRate / $periodsPerYear;

        if ($frequency === 'at_maturity') {
            // Bullet payment - principal + total interest at maturity
            $totalInterest = $principal * $annualRate * ($termMonths / 12);
            $regularPayment = $principal + $totalInterest;
        } elseif ($periodicRate > 0) {
            // Standard amortization formula
            $regularPayment = $principal * ($periodicRate * pow(1 + $periodicRate, $totalPayments))
                            / (pow(1 + $periodicRate, $totalPayments) - 1);
        } else {
            // Zero interest - simple division
            $regularPayment = $principal / $totalPayments;
        }

        $regularPayment = round($regularPayment, 2);
        $balance = $principal;
        $paymentDate = $startDate->copy();

        // Calculate date increment
        $dateIncrement = match($frequency) {
            'weekly' => '1 week',
            'bi_weekly' => '2 weeks',
            'monthly' => '1 month',
            'quarterly' => '3 months',
            'semi_annually' => '6 months',
            'annually' => '1 year',
            'at_maturity' => "{$termMonths} months",
            default => '1 month',
        };

        for ($i = 1; $i <= $totalPayments; $i++) {
            // Move to next payment date
            $paymentDate = $paymentDate->add(\DateInterval::createFromDateString($dateIncrement));

            // Calculate interest portion
            if ($interestType === 'flat') {
                // Flat rate - equal interest each period
                $interestPortion = round(($principal * $annualRate * ($termMonths / 12)) / $totalPayments, 2);
            } else {
                // Simple or compound - interest on remaining balance
                $interestPortion = round($balance * $periodicRate, 2);
            }

            // Calculate principal portion
            $principalPortion = $regularPayment - $interestPortion;

            // Adjust last payment for rounding
            if ($i == $totalPayments) {
                $principalPortion = $balance;
                $regularPayment = $principalPortion + $interestPortion;
            }

            $openingBalance = $balance;
            $balance = max(0, round($balance - $principalPortion, 2));

            LiabilityPaymentSchedule::create([
                'liability_id' => $liability->id,
                'payment_number' => $i,
                'due_date' => $paymentDate->toDateString(),
                'scheduled_payment' => $regularPayment,
                'principal_portion' => $principalPortion,
                'interest_portion' => $interestPortion,
                'opening_balance' => $openingBalance,
                'closing_balance' => $balance,
                'status' => 'scheduled',
            ]);
        }

        // Update liability with calculated regular payment and next payment date
        $firstPayment = $liability->paymentSchedules()->orderBy('due_date')->first();
        if ($firstPayment) {
            $liability->updateQuietly([
                'regular_payment_amount' => $regularPayment,
                'next_payment_date' => $firstPayment->due_date,
            ]);
        }

        // Update current/non-current portions
        $liability->updatePortions();

        Log::info('LiabilityScheduleObserver: Payment schedule generated', [
            'liability_id' => $liability->id,
            'total_payments' => $totalPayments,
            'regular_payment' => $regularPayment,
        ]);
    }
}
