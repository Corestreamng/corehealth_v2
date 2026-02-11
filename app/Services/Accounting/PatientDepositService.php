<?php

namespace App\Services\Accounting;

use App\Models\Accounting\PatientDeposit;
use App\Models\Accounting\PatientDepositApplication;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Account;
use App\Models\patient;
use App\Models\Billing\Bill;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Patient Deposit Service
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.9
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 3.2
 *
 * Manages patient deposits (advance payments) with JE-centric tracking.
 */
class PatientDepositService
{
    // Account codes
    private const PATIENT_DEPOSITS_LIABILITY = '2200';
    private const ACCOUNTS_RECEIVABLE = '1200';
    private const CASH_ACCOUNT = '1010';
    private const BANK_ACCOUNT = '1020';

    /**
     * Create a new patient deposit.
     */
    public function createDeposit(array $data): PatientDeposit
    {
        DB::beginTransaction();
        try {
            // Generate deposit number if not provided
            if (empty($data['deposit_number'])) {
                $data['deposit_number'] = PatientDeposit::generateNumber();
            }

            // Set defaults
            $data['deposit_date'] = $data['deposit_date'] ?? now()->toDateString();
            $data['utilized_amount'] = 0;
            $data['refunded_amount'] = 0;
            $data['status'] = PatientDeposit::STATUS_ACTIVE;
            $data['received_by'] = $data['received_by'] ?? auth()->id();

            // Create deposit - observer will create JE
            $deposit = PatientDeposit::create($data);

            DB::commit();

            Log::info('PatientDepositService: Deposit created', [
                'deposit_id' => $deposit->id,
                'patient_id' => $deposit->patient_id,
                'amount' => $deposit->amount,
            ]);

            return $deposit->fresh(['journalEntry', 'patient']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PatientDepositService: Failed to create deposit', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Apply deposit to a bill payment.
     */
    public function applyToPayment(
        PatientDeposit $deposit,
        Payment $payment,
        float $amount,
        ?int $appliedBy = null
    ): PatientDepositApplication {
        if (!$deposit->canApply($amount)) {
            throw new \InvalidArgumentException('Cannot apply amount: insufficient balance or inactive deposit');
        }

        DB::beginTransaction();
        try {
            // Create journal entry for application
            // DEBIT: Patient Deposits Liability (2200)
            // CREDIT: Accounts Receivable (1200)
            $debitAccount = Account::where('code', self::PATIENT_DEPOSITS_LIABILITY)->first();
            $creditAccount = Account::where('code', self::ACCOUNTS_RECEIVABLE)->first();

            if (!$debitAccount || !$creditAccount) {
                throw new \RuntimeException('Required accounts not found');
            }

            $journalEntry = JournalEntry::create([
                'entry_date' => now()->toDateString(),
                'reference_number' => "DEP-APP-{$deposit->deposit_number}-{$payment->id}",
                'reference_type' => 'patient_deposit_application',
                'description' => "Application of deposit {$deposit->deposit_number} to payment",
                'entry_type' => JournalEntry::TYPE_AUTO,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'created_by' => $appliedBy ?? auth()->id(),
            ]);

            // DEBIT: Patient Deposits Liability
            $patientName = $deposit->patient?->full_name ?? 'Unknown';
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $debitAccount->id,
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'description' => "Deposit applied to payment: {$patientName}",
                'metadata' => [
                    'patient_id' => $deposit->patient_id,
                    'deposit_id' => $deposit->id,
                    'payment_id' => $payment->id,
                ],
            ]);

            // CREDIT: Accounts Receivable
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $creditAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $amount,
                'description' => "AR reduced by deposit application",
                'metadata' => [
                    'patient_id' => $deposit->patient_id,
                    'payment_id' => $payment->id,
                ],
            ]);

            // Create application record
            $application = PatientDepositApplication::create([
                'deposit_id' => $deposit->id,
                'payment_id' => $payment->id,
                'bill_id' => $payment->bill_id ?? null,
                'journal_entry_id' => $journalEntry->id,
                'application_type' => PatientDepositApplication::TYPE_BILL_PAYMENT,
                'amount' => $amount,
                'application_date' => now()->toDateString(),
                'applied_by' => $appliedBy ?? auth()->id(),
                'status' => PatientDepositApplication::STATUS_APPLIED,
            ]);

            // Update deposit utilized amount
            $deposit->applyAmount($amount);

            DB::commit();

            Log::info('PatientDepositService: Deposit applied to payment', [
                'deposit_id' => $deposit->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'application_id' => $application->id,
            ]);

            return $application;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PatientDepositService: Failed to apply deposit', [
                'deposit_id' => $deposit->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process refund of remaining deposit balance.
     */
    public function processRefund(
        PatientDeposit $deposit,
        float $amount,
        string $reason,
        ?int $refundedBy = null
    ): PatientDeposit {
        if ($amount > $deposit->balance) {
            throw new \InvalidArgumentException('Refund amount exceeds available balance');
        }

        if (!$deposit->canBeRefunded()) {
            throw new \InvalidArgumentException('Deposit cannot be refunded');
        }

        DB::beginTransaction();
        try {
            // Refund method - observer will create JE
            $deposit->refund($amount, $reason, $refundedBy ?? auth()->id());

            DB::commit();

            Log::info('PatientDepositService: Deposit refunded', [
                'deposit_id' => $deposit->id,
                'refund_amount' => $amount,
                'reason' => $reason,
            ]);

            return $deposit->fresh(['refundJournalEntry']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PatientDepositService: Failed to refund deposit', [
                'deposit_id' => $deposit->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reverse a deposit application.
     */
    public function reverseApplication(
        PatientDepositApplication $application,
        string $reason,
        ?int $reversedBy = null
    ): PatientDepositApplication {
        if (!$application->canBeReversed()) {
            throw new \InvalidArgumentException('Application cannot be reversed');
        }

        DB::beginTransaction();
        try {
            // Create reversal journal entry
            $originalJe = $application->journalEntry;
            $deposit = $application->deposit;

            // Reversal JE: reverse the original entries
            // DEBIT: Accounts Receivable (1200)
            // CREDIT: Patient Deposits Liability (2200)
            $arAccount = Account::where('code', self::ACCOUNTS_RECEIVABLE)->first();
            $depositLiabilityAccount = Account::where('code', self::PATIENT_DEPOSITS_LIABILITY)->first();

            $reversalJe = JournalEntry::create([
                'entry_date' => now()->toDateString(),
                'reference_number' => "REV-{$application->id}",
                'reference_type' => 'deposit_application_reversal',
                'reference_id' => $application->id,
                'description' => "Reversal of deposit application: {$deposit->deposit_number}",
                'entry_type' => JournalEntry::TYPE_REVERSAL,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'created_by' => $reversedBy ?? auth()->id(),
            ]);

            // DEBIT: AR (reverse the credit)
            JournalEntryLine::create([
                'journal_entry_id' => $reversalJe->id,
                'account_id' => $arAccount->id,
                'debit_amount' => $application->amount,
                'credit_amount' => 0,
                'description' => "Reversal: restore AR",
            ]);

            // CREDIT: Patient Deposits Liability (reverse the debit)
            JournalEntryLine::create([
                'journal_entry_id' => $reversalJe->id,
                'account_id' => $depositLiabilityAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $application->amount,
                'description' => "Reversal: restore deposit liability",
            ]);

            // Reverse the application
            $application->reverse($reason, $reversedBy ?? auth()->id());

            DB::commit();

            Log::info('PatientDepositService: Application reversed', [
                'application_id' => $application->id,
                'deposit_id' => $deposit->id,
                'reason' => $reason,
            ]);

            return $application->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PatientDepositService: Failed to reverse application', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get patient's deposits with balances.
     */
    public function getPatientDeposits(int $patientId, bool $activeOnly = false): Collection
    {
        $query = PatientDeposit::forPatient($patientId)
            ->with(['journalEntry', 'applications', 'admission'])
            ->orderByDesc('deposit_date');

        if ($activeOnly) {
            $query->active()->withBalance();
        }

        return $query->get();
    }

    /**
     * Get available deposits for a patient (for applying to bills).
     */
    public function getAvailableDeposits(int $patientId): Collection
    {
        return PatientDeposit::forPatient($patientId)
            ->active()
            ->withBalance()
            ->with(['admission', 'applications'])
            ->orderBy('deposit_date')
            ->get();
    }

    /**
     * Get total available deposit balance for a patient.
     */
    public function getTotalAvailableBalance(int $patientId): float
    {
        return PatientDeposit::forPatient($patientId)
            ->active()
            ->selectRaw('SUM(amount - utilized_amount - refunded_amount) as total_balance')
            ->value('total_balance') ?? 0;
    }

    /**
     * Get deposit summary for dashboard.
     */
    public function getDepositSummary(?string $fromDate = null, ?string $toDate = null): array
    {
        $fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
        $toDate = $toDate ?? now()->toDateString();

        $deposits = PatientDeposit::forPeriod($fromDate, $toDate)->get();

        return [
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'total_deposits' => $deposits->sum('amount'),
            'total_utilized' => $deposits->sum('utilized_amount'),
            'total_refunded' => $deposits->sum('refunded_amount'),
            'total_outstanding' => $deposits->sum(fn ($d) => $d->balance),
            'deposit_count' => $deposits->count(),
            'active_count' => $deposits->where('status', PatientDeposit::STATUS_ACTIVE)->count(),
            'fully_applied_count' => $deposits->where('status', PatientDeposit::STATUS_FULLY_APPLIED)->count(),
            'refunded_count' => $deposits->where('status', PatientDeposit::STATUS_REFUNDED)->count(),
            'by_type' => $deposits->groupBy('deposit_type')->map(fn ($g) => [
                'count' => $g->count(),
                'total' => $g->sum('amount'),
                'utilized' => $g->sum('utilized_amount'),
            ])->toArray(),
        ];
    }

    /**
     * Get outstanding deposits report (for aging analysis).
     */
    public function getOutstandingDepositsReport(): Collection
    {
        return PatientDeposit::active()
            ->withBalance()
            ->with(['patient', 'admission'])
            ->orderBy('deposit_date')
            ->get()
            ->map(function ($deposit) {
                $daysOutstanding = Carbon::parse($deposit->deposit_date)->diffInDays(now());

                return [
                    'deposit' => $deposit,
                    'days_outstanding' => $daysOutstanding,
                    'aging_bucket' => $this->getAgingBucket($daysOutstanding),
                ];
            });
    }

    /**
     * Get aging bucket for a deposit.
     */
    private function getAgingBucket(int $days): string
    {
        return match (true) {
            $days <= 30 => '0-30 days',
            $days <= 60 => '31-60 days',
            $days <= 90 => '61-90 days',
            $days <= 180 => '91-180 days',
            default => 'Over 180 days',
        };
    }

    /**
     * Auto-apply available deposits to a payment.
     * Returns total amount applied from deposits.
     */
    public function autoApplyDeposits(Payment $payment, float $maxAmount): float
    {
        if (!$payment->patient_id) {
            return 0;
        }

        $availableDeposits = $this->getAvailableDeposits($payment->patient_id);
        $totalApplied = 0;
        $remainingAmount = $maxAmount;

        foreach ($availableDeposits as $deposit) {
            if ($remainingAmount <= 0) {
                break;
            }

            $applyAmount = min($deposit->balance, $remainingAmount);

            if ($applyAmount > 0) {
                $this->applyToPayment($deposit, $payment, $applyAmount);
                $totalApplied += $applyAmount;
                $remainingAmount -= $applyAmount;
            }
        }

        return $totalApplied;
    }
}
