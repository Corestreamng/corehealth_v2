<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\PettyCashFund;
use App\Models\Accounting\PettyCashTransaction;
use App\Models\Accounting\PettyCashReconciliation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Petty Cash Service
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.7
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 1.4-1.5
 *
 * Manages petty cash operations with JE-centric balance calculations.
 */
class PettyCashService
{
    /**
     * Get all petty cash funds with balances.
     */
    public function getAllFunds(bool $activeOnly = true): Collection
    {
        $query = PettyCashFund::with(['account', 'custodian', 'department']);

        if ($activeOnly) {
            $query->active();
        }

        return $query->get()->map(function ($fund) {
            $fund->computed_balance = $fund->getBalanceFromJournalEntries();
            return $fund;
        });
    }

    /**
     * Get fund by ID with balance.
     */
    public function getFundById(int $id): ?PettyCashFund
    {
        $fund = PettyCashFund::with(['account', 'custodian', 'department'])->find($id);

        if ($fund) {
            $fund->computed_balance = $fund->getBalanceFromJournalEntries();
        }

        return $fund;
    }

    /**
     * Get funds for a specific custodian.
     */
    public function getFundsForCustodian(int $userId): Collection
    {
        return PettyCashFund::with(['account', 'department'])
            ->active()
            ->forCustodian($userId)
            ->get()
            ->map(function ($fund) {
                $fund->computed_balance = $fund->getBalanceFromJournalEntries();
                return $fund;
            });
    }

    /**
     * Create a new petty cash fund.
     */
    public function createFund(array $data): PettyCashFund
    {
        // Generate fund code if not provided
        if (empty($data['fund_code'])) {
            $data['fund_code'] = $this->generateFundCode($data['fund_name']);
        }

        return PettyCashFund::create($data);
    }

    /**
     * Create a disbursement request.
     */
    public function createDisbursement(
        PettyCashFund $fund,
        array $data,
        int $requestedBy
    ): PettyCashTransaction {
        // Validate amount
        if ($data['amount'] > $fund->transaction_limit) {
            throw new \InvalidArgumentException(
                "Amount exceeds transaction limit of " . number_format($fund->transaction_limit, 2)
            );
        }

        // Check fund balance
        if (!$fund->canDisburse($data['amount'])) {
            throw new \InvalidArgumentException(
                "Insufficient fund balance for disbursement"
            );
        }

        // Create transaction
        $transaction = PettyCashTransaction::create([
            'fund_id' => $fund->id,
            'transaction_type' => PettyCashTransaction::TYPE_DISBURSEMENT,
            'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
            'voucher_number' => $fund->generateVoucherNumber(),
            'description' => $data['description'],
            'amount' => $data['amount'],
            'expense_category' => $data['expense_category'] ?? null,
            'expense_account_id' => $data['expense_account_id'] ?? null,
            'requested_by' => $requestedBy,
            'receipt_number' => $data['receipt_number'] ?? null,
            'payee_name' => $data['payee_name'] ?? null,
            'payee_type' => $data['payee_type'] ?? null,
            'status' => PettyCashTransaction::STATUS_PENDING,
        ]);

        // Auto-approve if below threshold and fund doesn't require approval
        if (!$fund->requires_approval ||
            ($fund->approval_threshold > 0 && $data['amount'] < $fund->approval_threshold)) {
            $this->approveTransaction($transaction, $requestedBy);
        }

        return $transaction;
    }

    /**
     * Create a replenishment request.
     *
     * @param PettyCashFund $fund The fund to replenish
     * @param float $amount The replenishment amount
     * @param string $description Description/reason for replenishment
     * @param int $requestedBy User ID who requested
     * @param string|null $paymentMethod 'cash' or 'bank_transfer'
     * @param int|null $bankId Bank ID if payment_method is 'bank_transfer'
     */
    public function createReplenishment(
        PettyCashFund $fund,
        float $amount,
        string $description,
        int $requestedBy,
        ?string $paymentMethod = 'bank_transfer',
        ?int $bankId = null
    ): PettyCashTransaction {
        return PettyCashTransaction::create([
            'fund_id' => $fund->id,
            'transaction_type' => PettyCashTransaction::TYPE_REPLENISHMENT,
            'transaction_date' => now()->toDateString(),
            'voucher_number' => $fund->generateVoucherNumber(),
            'description' => $description,
            'amount' => $amount,
            'requested_by' => $requestedBy,
            'payment_method' => $paymentMethod,
            'bank_id' => $paymentMethod === 'bank_transfer' ? $bankId : null,
            'status' => PettyCashTransaction::STATUS_PENDING,
        ]);
    }

    /**
     * Approve a transaction.
     */
    public function approveTransaction(PettyCashTransaction $transaction, int $approvedBy): PettyCashTransaction
    {
        if (!$transaction->canBeApproved()) {
            throw new \InvalidArgumentException("Transaction cannot be approved in current state");
        }

        $transaction->update([
            'status' => PettyCashTransaction::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $transaction->fresh();
    }

    /**
     * Disburse an approved transaction.
     *
     * This triggers the PettyCashObserver to create journal entry.
     */
    public function disburseTransaction(PettyCashTransaction $transaction): PettyCashTransaction
    {
        if (!$transaction->canBeDisbursed()) {
            throw new \InvalidArgumentException("Transaction cannot be disbursed in current state");
        }

        // Verify fund still has sufficient balance
        if ($transaction->transaction_type === PettyCashTransaction::TYPE_DISBURSEMENT) {
            if (!$transaction->fund->canDisburse($transaction->amount)) {
                throw new \InvalidArgumentException("Fund balance is insufficient for disbursement");
            }
        }

        $transaction->update([
            'status' => PettyCashTransaction::STATUS_DISBURSED,
        ]);

        return $transaction->fresh();
    }

    /**
     * Reject a transaction.
     */
    public function rejectTransaction(
        PettyCashTransaction $transaction,
        string $reason,
        int $rejectedBy
    ): PettyCashTransaction {
        if (!$transaction->isPending()) {
            throw new \InvalidArgumentException("Only pending transactions can be rejected");
        }

        $transaction->update([
            'status' => PettyCashTransaction::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approved_by' => $rejectedBy,
            'approved_at' => now(),
        ]);

        return $transaction->fresh();
    }

    /**
     * Void a transaction.
     */
    public function voidTransaction(PettyCashTransaction $transaction): PettyCashTransaction
    {
        if (!$transaction->canBeVoided()) {
            throw new \InvalidArgumentException("Transaction cannot be voided in current state");
        }

        // If already has journal entry, we need to reverse it
        if ($transaction->journal_entry_id) {
            // TODO: Implement journal entry reversal
            throw new \InvalidArgumentException(
                "Cannot void transaction with journal entry. Use reversal instead."
            );
        }

        $transaction->update([
            'status' => PettyCashTransaction::STATUS_VOIDED,
        ]);

        return $transaction->fresh();
    }

    /**
     * Start reconciliation for a fund.
     */
    public function startReconciliation(
        PettyCashFund $fund,
        float $actualCashCount,
        int $reconciledBy,
        ?array $denominationBreakdown = null
    ): PettyCashReconciliation {
        $expectedBalance = $fund->getBalanceFromJournalEntries();

        // Get outstanding vouchers (approved but not yet replenished)
        $outstandingVouchers = $fund->transactions()
            ->where('transaction_type', PettyCashTransaction::TYPE_DISBURSEMENT)
            ->where('status', PettyCashTransaction::STATUS_DISBURSED)
            ->whereNull('journal_entry_id')
            ->get();

        $outstandingAmount = $outstandingVouchers->sum('amount');
        $outstandingIds = $outstandingVouchers->pluck('id')->toArray();

        // Calculate variance
        $variance = round($expectedBalance - $actualCashCount, 2);

        // Determine status
        $status = match (true) {
            abs($variance) < 0.01 => PettyCashReconciliation::STATUS_BALANCED,
            $variance > 0 => PettyCashReconciliation::STATUS_SHORTAGE,
            default => PettyCashReconciliation::STATUS_OVERAGE,
        };

        return PettyCashReconciliation::create([
            'fund_id' => $fund->id,
            'reconciliation_date' => now()->toDateString(),
            'reconciliation_number' => PettyCashReconciliation::generateNumber($fund->id),
            'expected_balance' => $expectedBalance,
            'actual_cash_count' => $actualCashCount,
            'variance' => $variance,
            'denomination_breakdown' => $denominationBreakdown,
            'outstanding_vouchers' => $outstandingAmount,
            'outstanding_voucher_ids' => $outstandingIds,
            'status' => $status,
            'reconciled_by' => $reconciledBy,
        ]);
    }

    /**
     * Get pending transactions for approval.
     */
    public function getPendingTransactions(?int $fundId = null): Collection
    {
        $query = PettyCashTransaction::with(['fund', 'requester'])
            ->pending()
            ->orderBy('created_at', 'desc');

        if ($fundId) {
            $query->forFund($fundId);
        }

        return $query->get();
    }

    /**
     * Get transactions for a fund.
     */
    public function getTransactions(
        PettyCashFund $fund,
        ?string $fromDate = null,
        ?string $toDate = null
    ): Collection {
        $query = PettyCashTransaction::with(['requester', 'approver', 'journalEntry'])
            ->forFund($fund->id)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        if ($fromDate && $toDate) {
            $query->forPeriod($fromDate, $toDate);
        }

        return $query->get();
    }

    /**
     * Get fund summary for dashboard.
     */
    public function getFundSummary(PettyCashFund $fund): array
    {
        $currentBalance = $fund->getBalanceFromJournalEntries();
        $pending = $fund->getPendingDisbursements();

        // Get this month's transactions
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $monthlyStats = PettyCashTransaction::forFund($fund->id)
            ->forPeriod($monthStart, $monthEnd)
            ->disbursed()
            ->selectRaw('
                COUNT(*) as transaction_count,
                SUM(CASE WHEN transaction_type = "disbursement" THEN amount ELSE 0 END) as total_disbursed,
                SUM(CASE WHEN transaction_type = "replenishment" THEN amount ELSE 0 END) as total_replenished
            ')
            ->first();

        return [
            'fund_id' => $fund->id,
            'fund_name' => $fund->fund_name,
            'fund_code' => $fund->fund_code,
            'fund_limit' => $fund->fund_limit,
            'current_balance' => round($currentBalance, 2),
            'pending_disbursements' => round($pending, 2),
            'effective_balance' => round($currentBalance - $pending, 2),
            'utilization_percentage' => $fund->utilization_percentage,
            'needs_replenishment' => $fund->needsReplenishment(),
            'replenishment_amount' => round($fund->getReplenishmentAmount(), 2),
            'monthly_disbursed' => round($monthlyStats->total_disbursed ?? 0, 2),
            'monthly_replenished' => round($monthlyStats->total_replenished ?? 0, 2),
            'monthly_transactions' => $monthlyStats->transaction_count ?? 0,
        ];
    }

    /**
     * Generate fund code from name.
     */
    protected function generateFundCode(string $name): string
    {
        // Take first letter of each word, uppercase
        $words = preg_split('/\s+/', $name);
        $code = '';

        foreach ($words as $word) {
            if (strlen($word) > 0) {
                $code .= strtoupper($word[0]);
            }
        }

        // Ensure uniqueness
        $baseCode = $code;
        $counter = 1;

        while (PettyCashFund::where('fund_code', $code)->exists()) {
            $code = $baseCode . $counter;
            $counter++;
        }

        return $code;
    }

    /**
     * Get expense categories for dropdown.
     */
    public function getExpenseCategories(): array
    {
        return [
            'transport' => 'Transportation',
            'office_supplies' => 'Office Supplies',
            'refreshment' => 'Refreshment & Meals',
            'maintenance' => 'Maintenance & Repairs',
            'postage' => 'Postage & Courier',
            'utilities' => 'Utilities',
            'cleaning' => 'Cleaning',
            'miscellaneous' => 'Miscellaneous',
        ];
    }
}
