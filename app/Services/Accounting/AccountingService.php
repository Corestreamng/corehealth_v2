<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\AccountSubAccount;
use App\Models\Accounting\CreditNote;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\CashFlowClassifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Accounting Service
 *
 * Reference: Accounting System Plan §5 - Service Layer
 *
 * Core service for all accounting operations.
 * Handles journal entry creation, posting, reversal, and period management.
 *
 * THE SINGLE SOURCE OF TRUTH: All financial data flows through journal entries.
 */
class AccountingService
{
    /**
     * Create a manual journal entry.
     *
     * @param array $data Entry header data
     * @param array $lines Array of line items [['account_id', 'debit_amount', 'credit_amount', 'description', 'sub_account_id']]
     * @param int $userId Creator user ID
     * @return JournalEntry
     * @throws Exception
     */
    public function createManualEntry(array $data, array $lines, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($data, $lines, $userId) {
            // Validate the entry date and get period
            $entryDate = $data['entry_date'] ?? now()->toDateString();
            $period = $this->getOpenPeriodForDate($entryDate);

            if (!$period) {
                throw new Exception("No open accounting period for date: {$entryDate}");
            }

            // Create the journal entry
            $entry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $entryDate,
                'accounting_period_id' => $period->id,
                'description' => $data['description'] ?? '',
                'reference_type' => null,
                'reference_id' => null,
                'entry_type' => JournalEntry::TYPE_MANUAL,
                'status' => JournalEntry::STATUS_DRAFT,
                'created_by' => $userId,
            ]);

            // Create the lines
            $this->createJournalLines($entry, $lines);

            // Validate balance
            if (!$entry->fresh()->isBalanced()) {
                throw new Exception("Journal entry is not balanced. Debits must equal credits.");
            }

            Log::info("Manual journal entry created", [
                'entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'created_by' => $userId,
            ]);

            return $entry;
        });
    }

    /**
     * Create an automated journal entry from a source document.
     *
     * @param string $sourceType Source model class
     * @param int $sourceId Source model ID
     * @param string $description Entry description
     * @param array $lines Array of line items
     * @param string|null $entryDate Optional entry date (defaults to today)
     * @param int|null $userId Creator user ID (optional for system entries)
     * @return JournalEntry
     * @throws Exception
     */
    public function createAutomatedEntry(
        string $sourceType,
        int $sourceId,
        string $description,
        array $lines,
        ?string $entryDate = null,
        ?int $userId = null
    ): JournalEntry {
        return DB::transaction(function () use ($sourceType, $sourceId, $description, $lines, $entryDate, $userId) {
            $entryDate = $entryDate ?? now()->toDateString();
            $period = $this->getOpenPeriodForDate($entryDate);

            if (!$period) {
                throw new Exception("No open accounting period for date: {$entryDate}");
            }

            // Check if entry already exists for this source
            $existingEntry = JournalEntry::where('reference_type', $sourceType)
                ->where('reference_id', $sourceId)
                ->whereNotIn('status', [JournalEntry::STATUS_REVERSED])
                ->first();

            if ($existingEntry) {
                throw new Exception("Journal entry already exists for this source document.");
            }

            // Determine creator - use auth user, provided user, or default to user 1 (system)
            $creatorId = $userId ?? auth()->id() ?? 1;

            // Create the journal entry
            $entry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $entryDate,
                'accounting_period_id' => $period->id,
                'description' => $description,
                'reference_type' => $sourceType,
                'reference_id' => $sourceId,
                'entry_type' => JournalEntry::TYPE_AUTO,
                'status' => JournalEntry::STATUS_DRAFT,
                'created_by' => $creatorId,
            ]);

            // Create the lines
            $this->createJournalLines($entry, $lines);

            // Validate balance
            $entry->refresh();
            if (!$entry->isBalanced()) {
                throw new Exception("Automated journal entry is not balanced.");
            }

            Log::info("Automated journal entry created", [
                'entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'reference_type' => $sourceType,
                'reference_id' => $sourceId,
            ]);

            return $entry;
        });
    }

    /**
     * Create journal entry lines.
     *
     * @param JournalEntry $entry
     * @param array $lines
     *
     * Line array structure (metadata fields are optional):
     * [
     *     'account_id' => int,
     *     'sub_account_id' => int|null,
     *     'debit_amount' or 'debit' => float,
     *     'credit_amount' or 'credit' => float,
     *     'description' or 'narration' => string,
     *     // Metadata (optional - for granular filtering)
     *     'product_id' => int|null,
     *     'service_id' => int|null,
     *     'product_category_id' => int|null,
     *     'service_category_id' => int|null,
     *     'hmo_id' => int|null,
     *     'supplier_id' => int|null,
     *     'patient_id' => int|null,
     *     'department_id' => int|null,
     *     'category' => string|null,
     *     'cash_flow_category' => string|null, // 'operating', 'investing', 'financing' (auto-classified if not set)
     * ]
     */
    protected function createJournalLines(JournalEntry $entry, array $lines): void
    {
        $lineNumber = 1;
        $cashFlowClassifier = app(CashFlowClassifier::class);

        foreach ($lines as $line) {
            // Validate account exists and is active
            $account = Account::findOrFail($line['account_id']);
            if (!$account->is_active) {
                throw new Exception("Account '{$account->name}' is not active.");
            }

            // Validate sub-account if provided
            if (!empty($line['sub_account_id'])) {
                $subAccount = AccountSubAccount::where('id', $line['sub_account_id'])
                    ->where('account_id', $line['account_id'])
                    ->firstOrFail();
            }

            // Ensure only debit OR credit is set (support both naming conventions)
            $debit = floatval($line['debit_amount'] ?? $line['debit'] ?? 0);
            $credit = floatval($line['credit_amount'] ?? $line['credit'] ?? 0);

            if ($debit > 0 && $credit > 0) {
                throw new Exception("Line cannot have both debit and credit amounts.");
            }

            if ($debit == 0 && $credit == 0) {
                throw new Exception("Line must have either a debit or credit amount.");
            }

            // Auto-classify cash flow category (Reference: Part 1B)
            $cashFlowCategory = $cashFlowClassifier->classify(
                $line,
                $line['cash_flow_category'] ?? null
            );

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'line_number' => $lineNumber++,
                'account_id' => $line['account_id'],
                'sub_account_id' => $line['sub_account_id'] ?? null,
                'narration' => $line['description'] ?? $line['narration'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
                'cash_flow_category' => $cashFlowCategory,
                // Metadata fields for granular filtering (Reference: Part 5A)
                'product_id' => $line['product_id'] ?? null,
                'service_id' => $line['service_id'] ?? null,
                'product_category_id' => $line['product_category_id'] ?? null,
                'service_category_id' => $line['service_category_id'] ?? null,
                'hmo_id' => $line['hmo_id'] ?? null,
                'supplier_id' => $line['supplier_id'] ?? null,
                'patient_id' => $line['patient_id'] ?? null,
                'department_id' => $line['department_id'] ?? null,
                'category' => $line['category'] ?? null,
            ]);
        }
    }

    /**
     * Update a draft journal entry.
     *
     * @param JournalEntry $entry
     * @param array $data Header data
     * @param array $lines New lines (replaces existing)
     * @return JournalEntry
     * @throws Exception
     */
    public function updateEntry(JournalEntry $entry, array $data, array $lines): JournalEntry
    {
        if (!$entry->canEdit()) {
            throw new Exception("This journal entry cannot be edited.");
        }

        return DB::transaction(function () use ($entry, $data, $lines) {
            // Update entry date and check period
            if (isset($data['entry_date']) && $data['entry_date'] !== $entry->entry_date->toDateString()) {
                $period = $this->getOpenPeriodForDate($data['entry_date']);
                if (!$period) {
                    throw new Exception("No open accounting period for date: {$data['entry_date']}");
                }
                $entry->accounting_period_id = $period->id;
            }

            // Update header
            $entry->entry_date = $data['entry_date'] ?? $entry->entry_date;
            $entry->description = $data['description'] ?? $entry->description;
            $entry->save();

            // Delete existing lines and recreate
            $entry->lines()->delete();
            $this->createJournalLines($entry, $lines);

            // Validate balance
            $entry->refresh();
            if (!$entry->isBalanced()) {
                throw new Exception("Journal entry is not balanced.");
            }

            Log::info("Journal entry updated", [
                'entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
            ]);

            return $entry;
        });
    }

    /**
     * Submit a journal entry for approval.
     *
     * @param JournalEntry $entry
     * @param int $userId
     * @return bool
     * @throws Exception
     */
    public function submitForApproval(JournalEntry $entry, int $userId): bool
    {
        if (!$entry->canSubmit()) {
            throw new Exception("This journal entry cannot be submitted for approval.");
        }

        $result = $entry->submit($userId);

        if ($result) {
            Log::info("Journal entry submitted for approval", [
                'entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'submitted_by' => $userId,
            ]);
        }

        return $result;
    }

    /**
     * Approve a pending journal entry.
     *
     * @param JournalEntry $entry
     * @param int $userId
     * @return bool
     * @throws Exception
     */
    public function approveEntry(JournalEntry $entry, int $userId): bool
    {
        if (!$entry->canApprove()) {
            throw new Exception("This journal entry cannot be approved.");
        }

        $result = $entry->approve($userId);

        if ($result) {
            Log::info("Journal entry approved", [
                'entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'approved_by' => $userId,
            ]);
        }

        return $result;
    }

    /**
     * Reject a pending journal entry.
     *
     * @param JournalEntry $entry
     * @param int $userId
     * @param string $reason
     * @return bool
     * @throws Exception
     */
    public function rejectEntry(JournalEntry $entry, int $userId, string $reason): bool
    {
        if (!$entry->canReject()) {
            throw new Exception("This journal entry cannot be rejected.");
        }

        $result = $entry->reject($userId, $reason);

        if ($result) {
            Log::info("Journal entry rejected", [
                'entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'rejected_by' => $userId,
                'reason' => $reason,
            ]);
        }

        return $result;
    }

    /**
     * Post a journal entry (finalize it).
     *
     * @param JournalEntry $entry
     * @param int $userId
     * @return bool
     * @throws Exception
     */
    public function postEntry(JournalEntry $entry, int $userId): bool
    {
        if (!$entry->canPost()) {
            throw new Exception("This journal entry cannot be posted.");
        }

        $result = $entry->post($userId);

        if ($result) {
            Log::info("Journal entry posted", [
                'entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'posted_by' => $userId,
            ]);
        }

        return $result;
    }

    /**
     * Create and post an automated entry in one step.
     * Used for system-generated entries (payments, expenses, etc.)
     *
     * @param string $sourceType
     * @param int $sourceId
     * @param string $description
     * @param array $lines
     * @param string|null $entryDate
     * @param int|null $userId
     * @return JournalEntry
     */
    public function createAndPostAutomatedEntry(
        string $sourceType,
        int $sourceId,
        string $description,
        array $lines,
        ?string $entryDate = null,
        ?int $userId = null
    ): JournalEntry {
        return DB::transaction(function () use ($sourceType, $sourceId, $description, $lines, $entryDate, $userId) {
            $entry = $this->createAutomatedEntry($sourceType, $sourceId, $description, $lines, $entryDate, $userId);
            // Use auth user, provided user, or default to user 1 (system)
            $posterId = $userId ?? auth()->id() ?? 1;
            $this->postEntry($entry, $posterId);
            return $entry->fresh();
        });
    }

    /**
     * Reverse a posted journal entry.
     * Creates a new entry with opposite debits/credits.
     *
     * @param JournalEntry $entry
     * @param int $userId
     * @param string $reason
     * @param string|null $reversalDate
     * @return JournalEntry The reversing entry
     * @throws Exception
     */
    public function reverseEntry(JournalEntry $entry, int $userId, string $reason, ?string $reversalDate = null): JournalEntry
    {
        if (!$entry->canReverse()) {
            throw new Exception("This journal entry cannot be reversed.");
        }

        return DB::transaction(function () use ($entry, $userId, $reason, $reversalDate) {
            $reversalDate = $reversalDate ?? now()->toDateString();
            $period = $this->getOpenPeriodForDate($reversalDate);

            if (!$period) {
                throw new Exception("No open accounting period for reversal date: {$reversalDate}");
            }

            // Create reversing lines (swap debits and credits)
            $reversingLines = [];
            foreach ($entry->lines as $line) {
                $reversingLines[] = [
                    'account_id' => $line->account_id,
                    'sub_account_id' => $line->sub_account_id,
                    'description' => $line->narration,
                    'debit' => $line->credit, // Swap
                    'credit' => $line->debit, // Swap
                ];
            }

            // Create the reversing entry
            $reversingEntry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $reversalDate,
                'accounting_period_id' => $period->id,
                'description' => "Reversal of {$entry->entry_number}: {$reason}",
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'entry_type' => JournalEntry::TYPE_REVERSAL,
                'status' => JournalEntry::STATUS_DRAFT,
                'reversal_of_id' => $entry->id,
                'created_by' => $userId,
            ]);

            $this->createJournalLines($reversingEntry, $reversingLines);

            // Post the reversing entry immediately
            $reversingEntry->post($userId);

            // Mark original as reversed
            $entry->status = JournalEntry::STATUS_REVERSED;
            $entry->save();

            Log::info("Journal entry reversed", [
                'original_entry_id' => $entry->id,
                'reversing_entry_id' => $reversingEntry->id,
                'reversed_by' => $userId,
                'reason' => $reason,
            ]);

            return $reversingEntry;
        });
    }

    /**
     * Delete a draft journal entry.
     *
     * @param JournalEntry $entry
     * @return bool
     * @throws Exception
     */
    public function deleteEntry(JournalEntry $entry): bool
    {
        if (!$entry->canDelete()) {
            throw new Exception("This journal entry cannot be deleted.");
        }

        return DB::transaction(function () use ($entry) {
            $entryNumber = $entry->entry_number;
            $entry->lines()->delete();
            $entry->delete();

            Log::info("Journal entry deleted", [
                'entry_number' => $entryNumber,
            ]);

            return true;
        });
    }

    // =========================================
    // PERIOD MANAGEMENT
    // =========================================

    /**
     * Create a new fiscal year with monthly periods.
     *
     * @param string $name Fiscal year name (e.g., "2024")
     * @param Carbon $startDate Start date of fiscal year
     * @param Carbon $endDate End date of fiscal year
     * @return FiscalYear
     * @throws Exception
     */
    public function createFiscalYear(string $name, Carbon $startDate, Carbon $endDate): FiscalYear
    {
        return DB::transaction(function () use ($name, $startDate, $endDate) {
            // Validate date range
            if ($startDate->gte($endDate)) {
                throw new Exception("Fiscal year end date must be after start date.");
            }

            // Check for overlapping fiscal years
            $overlap = FiscalYear::where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                          ->where('end_date', '>=', $endDate);
                    });
            })->exists();

            if ($overlap) {
                throw new Exception("A fiscal year already exists that overlaps with this date range.");
            }

            // Create the fiscal year
            $fiscalYear = FiscalYear::create([
                'year_name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => FiscalYear::STATUS_OPEN,
            ]);

            // Generate monthly periods
            $currentDate = $startDate->copy();
            $periodNumber = 1;

            while ($currentDate->lt($endDate)) {
                $periodStart = $currentDate->copy();
                $periodEnd = $currentDate->copy()->endOfMonth();

                // Make sure the last period ends on the fiscal year end date
                if ($periodEnd->gt($endDate)) {
                    $periodEnd = $endDate->copy();
                }

                AccountingPeriod::create([
                    'fiscal_year_id' => $fiscalYear->id,
                    'period_name' => $periodStart->format('F Y'),
                    'period_number' => $periodNumber,
                    'start_date' => $periodStart,
                    'end_date' => $periodEnd,
                    'status' => AccountingPeriod::STATUS_OPEN,
                ]);

                $currentDate->addMonth()->startOfMonth();
                $periodNumber++;
            }

            Log::info("Fiscal year created", [
                'fiscal_year_id' => $fiscalYear->id,
                'name' => $name,
                'periods_created' => $periodNumber - 1,
            ]);

            return $fiscalYear->fresh(['periods']);
        });
    }

    /**
     * Get the open accounting period for a date.
     *
     * @param string|Carbon $date
     * @return AccountingPeriod|null
     */
    public function getOpenPeriodForDate(string|Carbon $date): ?AccountingPeriod
    {
        $carbonDate = $date instanceof Carbon ? $date : Carbon::parse($date);
        return AccountingPeriod::forDate($carbonDate);
    }

    /**
     * Get the current open accounting period.
     *
     * Returns the first open period where today's date falls within the period range,
     * or the most recent open period if none match today.
     *
     * @return AccountingPeriod|null
     */
    public function getCurrentPeriod(): ?AccountingPeriod
    {
        // First try to find an open period for today's date
        $period = AccountingPeriod::where('status', 'open')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->first();

        // If no period for today, get the most recent open period
        if (!$period) {
            $period = AccountingPeriod::where('status', 'open')
                ->orderBy('start_date', 'desc')
                ->first();
        }

        return $period;
    }

    /**
     * Close an accounting period.
     *
     * @param AccountingPeriod $period
     * @param int $userId
     * @return bool
     * @throws Exception
     */
    public function closePeriod(AccountingPeriod $period, int $userId): bool
    {
        if (!$period->canClose()) {
            throw new Exception("This accounting period cannot be closed.");
        }

        $period->status = AccountingPeriod::STATUS_CLOSED;
        $period->closed_by = $userId;
        $period->closed_at = now();

        $result = $period->save();

        if ($result) {
            Log::info("Accounting period closed", [
                'period_id' => $period->id,
                'period_name' => $period->name,
                'closed_by' => $userId,
            ]);
        }

        return $result;
    }

    /**
     * Reopen a closed accounting period.
     *
     * @param AccountingPeriod $period
     * @param int $userId
     * @return bool
     */
    public function reopenPeriod(AccountingPeriod $period, int $userId): bool
    {
        if ($period->fiscalYear && $period->fiscalYear->isClosed()) {
            throw new Exception("Cannot reopen period - fiscal year is closed.");
        }

        $period->status = AccountingPeriod::STATUS_OPEN;
        $period->closed_by = null;
        $period->closed_at = null;

        $result = $period->save();

        if ($result) {
            Log::info("Accounting period reopened", [
                'period_id' => $period->id,
                'period_name' => $period->name,
                'reopened_by' => $userId,
            ]);
        }

        return $result;
    }

    /**
     * Close a fiscal year and create closing entry.
     *
     * @param FiscalYear $fiscalYear
     * @param int $userId
     * @return JournalEntry The closing journal entry
     * @throws Exception
     */
    public function closeFiscalYear(FiscalYear $fiscalYear, int $userId): JournalEntry
    {
        if (!$fiscalYear->canClose()) {
            throw new Exception("This fiscal year cannot be closed.");
        }

        return DB::transaction(function () use ($fiscalYear, $userId) {
            // Close all open periods
            foreach ($fiscalYear->periods()->where('status', AccountingPeriod::STATUS_OPEN)->get() as $period) {
                $this->closePeriod($period, $userId);
            }

            // Create closing entry to transfer Income/Expense to Retained Earnings
            $closingEntry = $this->createYearEndClosingEntry($fiscalYear, $userId);

            // Mark fiscal year as closed
            $fiscalYear->status = FiscalYear::STATUS_CLOSED;
            $fiscalYear->closed_by = $userId;
            $fiscalYear->closed_at = now();
            $fiscalYear->save();

            Log::info("Fiscal year closed", [
                'fiscal_year_id' => $fiscalYear->id,
                'fiscal_year_name' => $fiscalYear->name,
                'closing_entry_id' => $closingEntry->id,
                'closed_by' => $userId,
            ]);

            return $closingEntry;
        });
    }

    /**
     * Create year-end closing entry.
     * Transfers net income/loss from temporary accounts to retained earnings.
     *
     * @param FiscalYear $fiscalYear
     * @param int $userId
     * @return JournalEntry
     */
    protected function createYearEndClosingEntry(FiscalYear $fiscalYear, int $userId): JournalEntry
    {
        $retainedEarningsAccount = Account::where('code', 'like', '%retained%')
            ->orWhere('name', 'like', '%Retained Earnings%')
            ->first();

        if (!$retainedEarningsAccount) {
            throw new Exception("Retained Earnings account not found. Please set up the account first.");
        }

        // Calculate total income and expenses for the fiscal year
        $incomeTotal = $this->getTemporaryAccountBalance('income', $fiscalYear);
        $expenseTotal = $this->getTemporaryAccountBalance('expense', $fiscalYear);

        $netIncome = $incomeTotal - $expenseTotal;

        if (abs($netIncome) < 0.01) {
            // No net income/loss, create a zero entry for documentation
            $netIncome = 0;
        }

        $lines = [];

        // Close income accounts (debit to zero out credit balances)
        if ($incomeTotal > 0) {
            $lines[] = [
                'account_id' => $this->getIncomeClosingAccountId(),
                'debit_amount' => $incomeTotal,
                'credit_amount' => 0,
                'description' => 'Close income accounts',
            ];
        }

        // Close expense accounts (credit to zero out debit balances)
        if ($expenseTotal > 0) {
            $lines[] = [
                'account_id' => $this->getExpenseClosingAccountId(),
                'debit_amount' => 0,
                'credit_amount' => $expenseTotal,
                'description' => 'Close expense accounts',
            ];
        }

        // Transfer net to Retained Earnings
        if ($netIncome > 0) {
            // Net Income: Credit Retained Earnings
            $lines[] = [
                'account_id' => $retainedEarningsAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $netIncome,
                'description' => 'Transfer net income to retained earnings',
            ];
        } elseif ($netIncome < 0) {
            // Net Loss: Debit Retained Earnings
            $lines[] = [
                'account_id' => $retainedEarningsAccount->id,
                'debit_amount' => abs($netIncome),
                'credit_amount' => 0,
                'description' => 'Transfer net loss to retained earnings',
            ];
        }

        // Get or create closing period
        $lastPeriod = $fiscalYear->periods()->orderBy('end_date', 'desc')->first();

        $entry = JournalEntry::create([
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $fiscalYear->end_date,
            'accounting_period_id' => $lastPeriod->id,
            'description' => "Year-end closing entry for {$fiscalYear->name}",
            'entry_type' => JournalEntry::TYPE_CLOSING,
            'status' => JournalEntry::STATUS_DRAFT,
            'is_manual' => false,
            'is_reversing' => false,
            'created_by' => $userId,
        ]);

        $this->createJournalLines($entry, $lines);
        $entry->post($userId);

        // Link to fiscal year
        $fiscalYear->retained_earnings_entry_id = $entry->id;
        $fiscalYear->save();

        return $entry;
    }

    /**
     * Get total balance for temporary accounts (income or expense).
     */
    protected function getTemporaryAccountBalance(string $type, FiscalYear $fiscalYear): float
    {
        // This would be implemented based on your chart of accounts structure
        // For now, return 0 - actual implementation depends on account class setup
        return 0;
    }

    protected function getIncomeClosingAccountId(): int
    {
        // Return income summary account ID
        // This would be configured in settings or found by account code
        return Account::where('name', 'like', '%Income Summary%')->firstOrFail()->id;
    }

    protected function getExpenseClosingAccountId(): int
    {
        // Return expense summary account ID
        return Account::where('name', 'like', '%Expense Summary%')->firstOrFail()->id;
    }

    // =========================================
    // CREDIT NOTE HANDLING
    // =========================================

    /**
     * Create journal entry for approved credit note.
     *
     * @param CreditNote $creditNote
     * @param int $userId
     * @return JournalEntry
     */
    public function createCreditNoteJournalEntry(CreditNote $creditNote, int $userId): JournalEntry
    {
        // Get configured accounts (these would come from settings)
        $revenueAccount = Account::where('name', 'like', '%Revenue%')->first();
        $refundPayableAccount = Account::where('name', 'like', '%Refund%')->first()
            ?? Account::where('name', 'like', '%Accounts Payable%')->first();

        $lines = [
            [
                'account_id' => $revenueAccount->id,
                'debit_amount' => $creditNote->total_amount,
                'credit_amount' => 0,
                'description' => "Credit note {$creditNote->credit_note_number} - {$creditNote->reason}",
            ],
            [
                'account_id' => $refundPayableAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $creditNote->total_amount,
                'description' => "Refund payable to patient: {$creditNote->patient->full_name}",
            ],
        ];

        $entry = $this->createAndPostAutomatedEntry(
            CreditNote::class,
            $creditNote->id,
            "Credit Note: {$creditNote->credit_note_number} - {$creditNote->patient->full_name}",
            $lines,
            $creditNote->credit_note_date->toDateString(),
            $userId
        );

        // Link entry to credit note
        $creditNote->journal_entry_id = $entry->id;
        $creditNote->save();

        return $entry;
    }

    /**
     * Preview the accounting impact of a credit note.
     * Returns the proposed journal lines without creating the entry.
     *
     * @param CreditNote $creditNote
     * @return array
     */
    public function previewCreditNoteImpact(CreditNote $creditNote): array
    {
        $revenueAccount = Account::where('name', 'like', '%Revenue%')->first();
        $refundPayableAccount = Account::where('name', 'like', '%Refund%')->first()
            ?? Account::where('name', 'like', '%Accounts Payable%')->first();

        return [
            'lines' => [
                [
                    'account' => $revenueAccount ? $revenueAccount->display_name : 'Revenue (TBD)',
                    'debit' => $creditNote->total_amount,
                    'credit' => 0,
                    'description' => 'Reverse revenue',
                ],
                [
                    'account' => $refundPayableAccount ? $refundPayableAccount->display_name : 'Refund Payable (TBD)',
                    'debit' => 0,
                    'credit' => $creditNote->total_amount,
                    'description' => 'Refund payable to patient',
                ],
            ],
            'total_debit' => $creditNote->total_amount,
            'total_credit' => $creditNote->total_amount,
            'is_balanced' => true,
        ];
    }
}
