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
use Carbon\Carbon;

/**
 * Lease Observer (IFRS 16 Compliant)
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.13
 *
 * Creates journal entries when a new lease is activated (IFRS 16).
 * Following JE-Centric Architecture - ALL numbers derive from journal_entries.
 *
 * Journal Entry on lease commencement (IFRS 16):
 *   DEBIT:  ROU Asset (1460 or configured)           - Right-of-Use Asset
 *   CREDIT: Lease Liability (2310 or configured)    - Lease Obligation
 *
 * For short-term/low-value leases (exempt from IFRS 16):
 *   DEBIT:  Rent Expense (6100)
 *   CREDIT: Bank/Cash (1020/1010)
 *
 * Account Codes Reference:
 * - 1010: Cash on Hand
 * - 1020: Bank Account
 * - 1460: Other Fixed Assets (ROU Asset default)
 * - 2310: Lease Obligations
 * - 6100: Rent Expense
 * - 6260: Depreciation Expense
 * - 6300: Interest Expense
 */
class LeaseObserver
{
    // Default account codes
    private const ROU_ASSET_DEFAULT = '1460';        // Other Fixed Assets
    private const LEASE_LIABILITY_DEFAULT = '2310';  // Lease Obligations
    private const DEPRECIATION_EXPENSE = '6260';     // Depreciation Expense
    private const INTEREST_EXPENSE = '6300';         // Interest Expense
    private const RENT_EXPENSE = '6100';             // Rent Expense
    private const BANK_ACCOUNT = '1020';             // Bank Account
    private const CASH_ACCOUNT = '1010';             // Cash on Hand

    /**
     * Handle the Lease "created" event.
     * Creates initial recognition JE for IFRS 16 leases.
     */
    public function created(Lease $lease): void
    {
        Log::info('LeaseObserver: created() called', ['lease_id' => $lease->id]);

        // Only create JE for active leases
        if ($lease->status !== Lease::STATUS_ACTIVE) {
            Log::info('LeaseObserver: Skipping JE for non-active lease', [
                'lease_id' => $lease->id,
                'status' => $lease->status
            ]);
            return;
        }

        // Check if exempt from IFRS 16
        if ($lease->isExemptFromIfrs16()) {
            Log::info('LeaseObserver: Lease is exempt from IFRS 16 recognition', [
                'lease_id' => $lease->id,
                'type' => $lease->lease_type
            ]);
            // For exempt leases, payments will be expensed as they occur
            $this->generatePaymentSchedule($lease);
            return;
        }

        try {
            DB::beginTransaction();

            // Calculate IFRS 16 values if not set
            if (!$lease->initial_lease_liability || $lease->initial_lease_liability == 0) {
                $lease->initial_lease_liability = $lease->calculateInitialLeaseLiability();
                $lease->current_lease_liability = $lease->initial_lease_liability;
            }

            if (!$lease->initial_rou_asset_value || $lease->initial_rou_asset_value == 0) {
                $lease->initial_rou_asset_value = $lease->calculateInitialRouAsset();
                $lease->current_rou_asset_value = $lease->initial_rou_asset_value;
            }

            // Get ROU Asset account
            $rouAccount = Account::find($lease->rou_asset_account_id)
                ?? Account::where('code', self::ROU_ASSET_DEFAULT)->first();

            // Get Lease Liability account
            $liabilityAccount = Account::find($lease->lease_liability_account_id)
                ?? Account::where('code', self::LEASE_LIABILITY_DEFAULT)->first();

            if (!$rouAccount || !$liabilityAccount) {
                Log::error('LeaseObserver: Required accounts not found', [
                    'lease_id' => $lease->id,
                    'rou_account' => $rouAccount ? 'found' : 'missing',
                    'liability_account' => $liabilityAccount ? 'found' : 'missing',
                ]);
                DB::rollBack();
                return;
            }

            $leaseTypeLabel = $lease->lease_type_label;

            // Create initial recognition journal entry
            $journalEntry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'accounting_period_id' => AccountingPeriod::current()?->id,
                'entry_date' => $lease->commencement_date,
                'reference_number' => "LSE-{$lease->lease_number}",
                'reference_type' => 'lease',
                'reference_id' => $lease->id,
                'description' => "IFRS 16 initial recognition - {$leaseTypeLabel}: {$lease->leased_item} ({$lease->lease_number})",
                'entry_type' => JournalEntry::TYPE_AUTO,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'created_by' => $lease->created_by ?? auth()->id() ?? 1,
            ]);

            // Line 1: DEBIT ROU Asset
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => 1,
                'account_id' => $rouAccount->id,
                'debit' => $lease->initial_rou_asset_value,
                'credit' => 0,
                'narration' => "Right-of-Use Asset: {$lease->leased_item}",
                'metadata' => [
                    'lease_id' => $lease->id,
                    'lease_number' => $lease->lease_number,
                    'lease_type' => $lease->lease_type,
                    'lessor' => $lease->lessor_name,
                    'term_months' => $lease->lease_term_months,
                    'initial_direct_costs' => $lease->initial_direct_costs,
                    'lease_incentives' => $lease->lease_incentives_received,
                ],
            ]);

            // Line 2: CREDIT Lease Liability
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => 2,
                'account_id' => $liabilityAccount->id,
                'debit' => 0,
                'credit' => $lease->initial_lease_liability,
                'narration' => "Lease liability: {$lease->leased_item}",
                'metadata' => [
                    'lease_id' => $lease->id,
                    'lease_number' => $lease->lease_number,
                    'incremental_borrowing_rate' => $lease->incremental_borrowing_rate,
                    'monthly_payment' => $lease->monthly_payment,
                    'total_payments' => $lease->total_lease_payments,
                ],
            ]);

            // Handle initial direct costs and lease incentives difference
            $difference = $lease->initial_rou_asset_value - $lease->initial_lease_liability;
            if (abs($difference) > 0.01) {
                // This difference is due to initial direct costs - lease incentives
                // Already captured in ROU asset value, so no additional entry needed
                Log::info('LeaseObserver: ROU vs Liability difference (initial costs/incentives)', [
                    'difference' => $difference,
                    'initial_direct_costs' => $lease->initial_direct_costs,
                    'lease_incentives' => $lease->lease_incentives_received,
                ]);
            }

            // Update lease with journal entry link
            $lease->updateQuietly([
                'initial_lease_liability' => $lease->initial_lease_liability,
                'initial_rou_asset_value' => $lease->initial_rou_asset_value,
                'current_lease_liability' => $lease->current_lease_liability,
                'current_rou_asset_value' => $lease->current_rou_asset_value,
            ]);

            DB::commit();

            Log::info('LeaseObserver: JE created for lease initial recognition', [
                'lease_id' => $lease->id,
                'lease_number' => $lease->lease_number,
                'journal_entry_id' => $journalEntry->id,
                'rou_asset' => $lease->initial_rou_asset_value,
                'lease_liability' => $lease->initial_lease_liability,
            ]);

            // Generate payment schedule
            $this->generatePaymentSchedule($lease);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('LeaseObserver: Failed to create JE', [
                'lease_id' => $lease->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Generate amortization schedule for the lease.
     */
    protected function generatePaymentSchedule(Lease $lease): void
    {
        // Don't regenerate if payments already exist
        if (LeasePaymentSchedule::where('lease_id', $lease->id)->exists()) {
            return;
        }

        $monthlyPayment = (float) $lease->monthly_payment;
        $monthlyRate = ((float) $lease->incremental_borrowing_rate / 100) / 12;
        $escalationRate = (float) $lease->annual_rent_increase_rate / 100;
        $termMonths = (int) $lease->lease_term_months;
        $startDate = Carbon::parse($lease->commencement_date);

        $openingLiability = (float) $lease->initial_lease_liability;
        $openingRouValue = (float) $lease->initial_rou_asset_value;
        $monthlyDepreciation = $termMonths > 0 ? $openingRouValue / $termMonths : 0;

        $currentPayment = $monthlyPayment;
        $isIfrs16 = !$lease->isExemptFromIfrs16();

        for ($i = 1; $i <= $termMonths; $i++) {
            $dueDate = $startDate->copy()->addMonths($i - 1);

            // Apply annual escalation at the start of each year (month 13, 25, etc.)
            if ($escalationRate > 0 && $i > 1 && ($i - 1) % 12 === 0) {
                $currentPayment *= (1 + $escalationRate);
            }

            // Calculate interest and principal for IFRS 16 leases
            if ($isIfrs16) {
                $interestPortion = round($openingLiability * $monthlyRate, 2);
                $principalPortion = round($currentPayment - $interestPortion, 2);
                $closingLiability = round(max(0, $openingLiability - $principalPortion), 2);
                $closingRouValue = round(max(0, $openingRouValue - $monthlyDepreciation), 2);
            } else {
                // For exempt leases, entire payment is expense
                $interestPortion = 0;
                $principalPortion = 0;
                $closingLiability = 0;
                $closingRouValue = 0;
            }

            LeasePaymentSchedule::create([
                'lease_id' => $lease->id,
                'payment_number' => $i,
                'due_date' => $dueDate,
                'payment_amount' => round($currentPayment, 2),
                'principal_portion' => $principalPortion,
                'interest_portion' => $interestPortion,
                'opening_liability' => round($openingLiability, 2),
                'closing_liability' => $closingLiability,
                'rou_depreciation' => $isIfrs16 ? round($monthlyDepreciation, 2) : 0,
                'opening_rou_value' => $isIfrs16 ? round($openingRouValue, 2) : 0,
                'closing_rou_value' => $closingRouValue,
                'status' => LeasePaymentSchedule::STATUS_SCHEDULED,
            ]);

            // Update for next iteration
            $openingLiability = $closingLiability;
            $openingRouValue = $closingRouValue;
        }

        Log::info('LeaseObserver: Payment schedule generated', [
            'lease_id' => $lease->id,
            'payments' => $termMonths,
            'is_ifrs16' => $isIfrs16,
        ]);
    }
}
