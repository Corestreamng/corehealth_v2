<?php

namespace App\Observers\Accounting;

use App\Models\CapexProjectExpense;
use App\Models\CapexProject;
use App\Models\Accounting\Account;
use App\Models\Bank;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * CAPEX Expense Observer
 *
 * Creates journal entries when CAPEX expenses are recorded.
 *
 * DEBIT:  Fixed Asset Account (based on project type/category)
 * CREDIT: Bank Account (based on payment method) or Accounts Payable
 *
 * Fixed Asset Account Mappings (14xx range):
 * - Medical Equipment: 1400
 * - Furniture & Fixtures: 1410
 * - Computer/IT Equipment: 1420
 * - Vehicles: 1430
 * - Building/Renovation: 1440
 * - Land: 1450
 * - Other Fixed Assets: 1460 (Default for unclassified CAPEX)
 *
 * Note: Accumulated Depreciation accounts are in 15xx range (contra-assets)
 */
class CapexExpenseObserver
{
    /**
     * Handle the CapexProjectExpense "created" event.
     * Creates journal entry when expense is recorded.
     */
    public function created(CapexProjectExpense $expense): void
    {
        // Only create journal entry if status is approved or paid
        if (!in_array($expense->status, [CapexProjectExpense::STATUS_APPROVED, CapexProjectExpense::STATUS_PAID])) {
            return;
        }

        try {
            $this->createCapexJournalEntry($expense);
        } catch (\Exception $e) {
            Log::error('CapexExpenseObserver: Failed to create journal entry', [
                'expense_id' => $expense->id,
                'project_id' => $expense->project_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle the CapexProjectExpense "updated" event.
     */
    public function updated(CapexProjectExpense $expense): void
    {
        // Create journal entry when expense is approved
        if ($expense->isDirty('status') && in_array($expense->status, [CapexProjectExpense::STATUS_APPROVED, CapexProjectExpense::STATUS_PAID])) {
            if (!$expense->journal_entry_id) {
                try {
                    $this->createCapexJournalEntry($expense);
                } catch (\Exception $e) {
                    Log::error('CapexExpenseObserver: Failed to create journal entry on update', [
                        'expense_id' => $expense->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Reverse journal entry when expense is voided
        if ($expense->isDirty('status') && $expense->status === CapexProjectExpense::STATUS_VOID) {
            try {
                $this->reverseCapexJournalEntry($expense);
            } catch (\Exception $e) {
                Log::error('CapexExpenseObserver: Failed to reverse journal entry', [
                    'expense_id' => $expense->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create journal entry for CAPEX expense.
     */
    protected function createCapexJournalEntry(CapexProjectExpense $expense): void
    {
        $accountingService = App::make(AccountingService::class);

        $project = $expense->project;
        if (!$project) {
            Log::warning('CapexExpenseObserver: Skipped - project not found', [
                'expense_id' => $expense->id,
            ]);
            return;
        }

        $debitAccountCode = $this->getDebitAccountCode($project);
        $creditAccountCode = $this->getCreditAccountCode($expense);

        $debitAccount = Account::where('code', $debitAccountCode)->first();
        $creditAccount = Account::where('code', $creditAccountCode)->first();

        if (!$debitAccount || !$creditAccount) {
            Log::warning('CapexExpenseObserver: Skipped - accounts not configured', [
                'expense_id' => $expense->id,
                'debit_code' => $debitAccountCode,
                'credit_code' => $creditAccountCode,
            ]);
            return;
        }

        $description = $this->buildDescription($expense, $project);

        $lines = [
            [
                'account_id' => $debitAccount->id,
                'debit_amount' => $expense->amount,
                'credit_amount' => 0,
                'description' => "CAPEX: {$project->project_name} - {$expense->description}",
                'category' => 'capex',
            ],
            [
                'account_id' => $creditAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $expense->amount,
                'description' => $this->buildCreditDescription($expense),
                'category' => 'capex',
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            CapexProjectExpense::class,
            $expense->id,
            $description,
            $lines
        );

        // Update expense with journal entry reference
        $expense->journal_entry_id = $entry->id;
        $expense->saveQuietly();

        // Update project actual_cost
        $this->updateProjectActualCost($project);

        Log::info('CapexExpenseObserver: Journal entry created', [
            'expense_id' => $expense->id,
            'project_id' => $project->id,
            'journal_entry_id' => $entry->id,
            'amount' => $expense->amount,
            'debit_account' => $debitAccountCode,
            'credit_account' => $creditAccountCode,
        ]);
    }

    /**
     * Get debit account code based on project type/category.
     * Maps to Fixed Assets accounts (14xx range).
     *
     * Fixed Asset Accounts:
     * - 1400: Medical Equipment
     * - 1410: Furniture & Fixtures
     * - 1420: Computer Equipment
     * - 1430: Vehicles
     * - 1440: Building
     * - 1450: Land
     * - 1460: Other Fixed Assets (Default)
     */
    protected function getDebitAccountCode(CapexProject $project): string
    {
        // Use project_type or category to determine asset account
        $type = strtolower($project->project_type ?? $project->category ?? '');

        return match (true) {
            str_contains($type, 'equipment') || str_contains($type, 'machinery') || str_contains($type, 'medical') => '1400', // Medical Equipment
            str_contains($type, 'furniture') || str_contains($type, 'fixture') => '1410', // Furniture & Fixtures
            str_contains($type, 'technology') || str_contains($type, 'it') || str_contains($type, 'software') || str_contains($type, 'computer') => '1420', // Computer Equipment
            str_contains($type, 'vehicle') || str_contains($type, 'transport') => '1430', // Vehicles
            str_contains($type, 'building') || str_contains($type, 'renovation') || str_contains($type, 'improvement') || str_contains($type, 'construction') => '1440', // Building
            str_contains($type, 'land') || str_contains($type, 'property') => '1450', // Land
            default => '1460' // Default to Other Fixed Assets
        };
    }

    /**
     * Get credit account code based on payment method.
     * Uses specific bank's GL account if bank_id is set.
     */
    protected function getCreditAccountCode(CapexProjectExpense $expense): string
    {
        // If vendor is involved and not paid immediately, credit AP
        if ($expense->vendor && !$expense->payment_method) {
            return '2100'; // Accounts Payable
        }

        // If bank_id is set, use that bank's GL account
        if ($expense->bank_id) {
            $bank = Bank::find($expense->bank_id);
            if ($bank && $bank->account_id) {
                $account = Account::find($bank->account_id);
                if ($account) {
                    Log::info('CapexExpenseObserver: Using bank-specific GL account', [
                        'expense_id' => $expense->id,
                        'bank_id' => $bank->id,
                        'bank_name' => $bank->name,
                        'account_code' => $account->code,
                    ]);
                    return $account->code;
                }
            }
        }

        // Fallback to generic account codes
        return match (strtolower($expense->payment_method ?? 'cash')) {
            'cash' => '1010', // Cash in Hand
            'bank_transfer', 'bank', 'transfer' => '1020', // Bank Account
            'cheque', 'check' => '1020', // Bank Account
            'card' => '1020', // Bank Account (card linked)
            default => '1010' // Default to Cash
        };
    }

    /**
     * Build description for journal entry.
     */
    protected function buildDescription(CapexProjectExpense $expense, CapexProject $project): string
    {
        $parts = [
            "CAPEX Expense: {$project->project_name}",
            "Project Code: " . ($project->project_code ?? $project->reference_number ?? 'N/A'),
            "Description: {$expense->description}",
            "Amount: ₦" . number_format($expense->amount, 2),
        ];

        if ($expense->vendor) {
            $parts[] = "Vendor: {$expense->vendor}";
        }

        if ($expense->invoice_number) {
            $parts[] = "Invoice: {$expense->invoice_number}";
        }

        if ($expense->payment_method) {
            $parts[] = "Payment: {$expense->payment_method}";
        }

        return implode(' | ', $parts);
    }

    /**
     * Build credit line description.
     */
    protected function buildCreditDescription(CapexProjectExpense $expense): string
    {
        if ($expense->vendor && !$expense->payment_method) {
            return "Accounts Payable - {$expense->vendor}";
        }

        $method = ucfirst($expense->payment_method ?? 'Cash');
        if ($expense->bank_id) {
            $bank = Bank::find($expense->bank_id);
            if ($bank) {
                return "Paid via {$method} - {$bank->name}";
            }
        }

        if ($expense->cheque_number) {
            return "Paid via Cheque #{$expense->cheque_number}";
        }

        return "Paid via {$method}";
    }

    /**
     * Update project actual cost based on expenses.
     */
    protected function updateProjectActualCost(CapexProject $project): void
    {
        $totalExpenses = CapexProjectExpense::where('project_id', $project->id)
            ->whereIn('status', [CapexProjectExpense::STATUS_APPROVED, CapexProjectExpense::STATUS_PAID])
            ->sum('amount');

        $project->actual_cost = $totalExpenses;
        $project->saveQuietly();
    }

    /**
     * Reverse journal entry when expense is voided.
     */
    protected function reverseCapexJournalEntry(CapexProjectExpense $expense): void
    {
        if (!$expense->journal_entry_id) {
            return;
        }

        $accountingService = App::make(AccountingService::class);

        $accountingService->reverseEntry(
            $expense->journal_entry_id,
            "Reversal: CAPEX Expense voided - {$expense->description}"
        );

        Log::info('CapexExpenseObserver: Journal entry reversed', [
            'expense_id' => $expense->id,
            'journal_entry_id' => $expense->journal_entry_id,
        ]);

        // Update project actual cost
        if ($expense->project) {
            $this->updateProjectActualCost($expense->project);
        }
    }
}
