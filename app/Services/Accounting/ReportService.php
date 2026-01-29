<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountClass;
use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Report Service
 *
 * Reference: Accounting System Plan §5 - Service Layer
 *
 * Generates all accounting reports.
 * All reports derive data from posted journal entries.
 */
class ReportService
{
    /**
     * Generate Trial Balance report.
     *
     * @param string $asOfDate Date for balance calculation
     * @param bool $showZeroBalances Include accounts with zero balance
     * @param int|null $fiscalYearId Filter by fiscal year
     * @return array
     */
    public function generateTrialBalance(string $asOfDate, bool $showZeroBalances = false, ?int $fiscalYearId = null): array
    {
        $accounts = Account::with(['accountGroup.accountClass'])
            ->active()
            ->orderBy('code')
            ->get();

        $data = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $balance = $account->getBalance(null, $asOfDate);

            if (!$showZeroBalances && abs($balance) < 0.01) {
                continue;
            }

            $debit = $account->isDebitBalance() ? max(0, $balance) : max(0, -$balance);
            $credit = $account->isDebitBalance() ? max(0, -$balance) : max(0, $balance);

            // Adjust for contra accounts
            if ($balance < 0 && $account->isDebitBalance()) {
                $debit = 0;
                $credit = abs($balance);
            } elseif ($balance < 0 && !$account->isDebitBalance()) {
                $debit = abs($balance);
                $credit = 0;
            }

            $data[] = [
                'account_id' => $account->id,
                'account_code' => $account->full_code,
                'account_name' => $account->name,
                'class_name' => $account->accountGroup->accountClass->name ?? '',
                'group_name' => $account->accountGroup->name ?? '',
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        return [
            'as_of_date' => $asOfDate,
            'generated_at' => now()->toIso8601String(),
            'accounts' => $data,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
            'difference' => round($totalDebit - $totalCredit, 2),
        ];
    }

    /**
     * Generate Profit & Loss (Income Statement) report.
     *
     * @param string $fromDate
     * @param string $toDate
     * @param bool $compareWithPrevious Compare with previous period
     * @return array
     */
    public function generateProfitAndLoss(string $fromDate, string $toDate, bool $compareWithPrevious = false): array
    {
        $incomeClass = AccountClass::where('code', AccountClass::CODE_INCOME)->first();
        $expenseClass = AccountClass::where('code', AccountClass::CODE_EXPENSE)->first();

        $incomeData = $this->getClassAccountsWithBalances($incomeClass, $fromDate, $toDate);
        $expenseData = $this->getClassAccountsWithBalances($expenseClass, $fromDate, $toDate);

        $totalIncome = collect($incomeData['accounts'])->sum('balance');
        $totalExpenses = collect($expenseData['accounts'])->sum('balance');
        $netIncome = $totalIncome - $totalExpenses;

        $result = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'generated_at' => now()->toIso8601String(),
            'income' => $incomeData,
            'expenses' => $expenseData,
            'total_income' => round($totalIncome, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_income' => round($netIncome, 2),
            'is_profit' => $netIncome >= 0,
        ];

        // Add comparison with previous period if requested
        if ($compareWithPrevious) {
            $periodDays = (strtotime($toDate) - strtotime($fromDate)) / 86400;
            $prevFromDate = date('Y-m-d', strtotime($fromDate) - ($periodDays * 86400));
            $prevToDate = date('Y-m-d', strtotime($toDate) - ($periodDays * 86400));

            $prevIncomeData = $this->getClassAccountsWithBalances($incomeClass, $prevFromDate, $prevToDate);
            $prevExpenseData = $this->getClassAccountsWithBalances($expenseClass, $prevFromDate, $prevToDate);

            $prevTotalIncome = collect($prevIncomeData['accounts'])->sum('balance');
            $prevTotalExpenses = collect($prevExpenseData['accounts'])->sum('balance');
            $prevNetIncome = $prevTotalIncome - $prevTotalExpenses;

            $result['comparison'] = [
                'from_date' => $prevFromDate,
                'to_date' => $prevToDate,
                'total_income' => round($prevTotalIncome, 2),
                'total_expenses' => round($prevTotalExpenses, 2),
                'net_income' => round($prevNetIncome, 2),
                'income_change' => round($totalIncome - $prevTotalIncome, 2),
                'expense_change' => round($totalExpenses - $prevTotalExpenses, 2),
                'net_income_change' => round($netIncome - $prevNetIncome, 2),
            ];
        }

        return $result;
    }

    /**
     * Generate Balance Sheet report.
     *
     * @param string $asOfDate
     * @return array
     */
    public function generateBalanceSheet(string $asOfDate): array
    {
        $assetClass = AccountClass::where('code', AccountClass::CODE_ASSET)->first();
        $liabilityClass = AccountClass::where('code', AccountClass::CODE_LIABILITY)->first();
        $equityClass = AccountClass::where('code', AccountClass::CODE_EQUITY)->first();

        $assetData = $this->getClassAccountsWithBalances($assetClass, null, $asOfDate);
        $liabilityData = $this->getClassAccountsWithBalances($liabilityClass, null, $asOfDate);
        $equityData = $this->getClassAccountsWithBalances($equityClass, null, $asOfDate);

        $totalAssets = collect($assetData['accounts'])->sum('balance');
        $totalLiabilities = collect($liabilityData['accounts'])->sum('balance');
        $totalEquity = collect($equityData['accounts'])->sum('balance');

        // Calculate retained earnings (YTD net income if not yet closed)
        $ytdNetIncome = $this->calculateYTDNetIncome($asOfDate);
        $totalEquityWithRetained = $totalEquity + $ytdNetIncome;

        return [
            'as_of_date' => $asOfDate,
            'generated_at' => now()->toIso8601String(),
            'assets' => $assetData,
            'liabilities' => $liabilityData,
            'equity' => $equityData,
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity' => round($totalEquity, 2),
            'ytd_net_income' => round($ytdNetIncome, 2),
            'total_equity_with_retained' => round($totalEquityWithRetained, 2),
            'total_liabilities_and_equity' => round($totalLiabilities + $totalEquityWithRetained, 2),
            'is_balanced' => abs($totalAssets - ($totalLiabilities + $totalEquityWithRetained)) < 0.01,
        ];
    }

    /**
     * Generate General Ledger report for a specific account.
     *
     * @param int $accountId
     * @param string $fromDate
     * @param string $toDate
     * @return array
     */
    public function generateGeneralLedger(int $accountId, string $fromDate, string $toDate): array
    {
        $account = Account::with('accountGroup.accountClass')->findOrFail($accountId);

        // Get opening balance
        $openingBalance = $account->getBalance(null, date('Y-m-d', strtotime($fromDate) - 86400));

        // Get all posted journal lines for this account
        $lines = JournalEntryLine::with(['journalEntry'])
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($fromDate, $toDate) {
                $q->where('status', JournalEntry::STATUS_POSTED)
                    ->whereBetween('entry_date', [$fromDate, $toDate]);
            })
            ->get()
            ->sortBy(function ($line) {
                return $line->journalEntry->entry_date . $line->journalEntry->entry_number;
            });

        $transactions = [];
        $runningBalance = $openingBalance;

        foreach ($lines as $line) {
            // Calculate running balance based on normal balance type
            if ($account->isDebitBalance()) {
                $runningBalance += $line->debit - $line->credit;
            } else {
                $runningBalance += $line->credit - $line->debit;
            }

            $transactions[] = [
                'date' => $line->journalEntry->entry_date->format('Y-m-d'),
                'entry_number' => $line->journalEntry->entry_number,
                'entry_id' => $line->journalEntry->id,
                'description' => $line->description ?: $line->journalEntry->description,
                'debit' => round($line->debit, 2),
                'credit' => round($line->credit, 2),
                'running_balance' => round($runningBalance, 2),
                'source_type' => $line->journalEntry->source_type_label,
            ];
        }

        $totalDebit = $account->getDebitTotal($fromDate, $toDate);
        $totalCredit = $account->getCreditTotal($fromDate, $toDate);
        $closingBalance = $runningBalance;

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->full_code,
                'name' => $account->name,
                'class' => $account->accountGroup->accountClass->name ?? '',
                'group' => $account->accountGroup->name ?? '',
                'normal_balance' => $account->normal_balance,
            ],
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'generated_at' => now()->toIso8601String(),
            'opening_balance' => round($openingBalance, 2),
            'transactions' => $transactions,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'closing_balance' => round($closingBalance, 2),
        ];
    }

    /**
     * Generate Cash Flow Statement.
     *
     * @param string $fromDate
     * @param string $toDate
     * @return array
     */
    public function generateCashFlowStatement(string $fromDate, string $toDate): array
    {
        // Get cash and bank accounts
        $cashAccounts = Account::where('is_bank_account', true)
            ->orWhere('name', 'like', '%Cash%')
            ->get();

        $operatingActivities = $this->getCashFlowByCategory(AccountClass::CASH_FLOW_OPERATING, $fromDate, $toDate);
        $investingActivities = $this->getCashFlowByCategory(AccountClass::CASH_FLOW_INVESTING, $fromDate, $toDate);
        $financingActivities = $this->getCashFlowByCategory(AccountClass::CASH_FLOW_FINANCING, $fromDate, $toDate);

        $beginningCash = 0;
        $endingCash = 0;
        foreach ($cashAccounts as $account) {
            $beginningCash += $account->getBalance(null, date('Y-m-d', strtotime($fromDate) - 86400));
            $endingCash += $account->getBalance(null, $toDate);
        }

        $netOperating = collect($operatingActivities)->sum('amount');
        $netInvesting = collect($investingActivities)->sum('amount');
        $netFinancing = collect($financingActivities)->sum('amount');
        $netChange = $netOperating + $netInvesting + $netFinancing;

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'generated_at' => now()->toIso8601String(),
            'operating_activities' => $operatingActivities,
            'investing_activities' => $investingActivities,
            'financing_activities' => $financingActivities,
            'net_operating' => round($netOperating, 2),
            'net_investing' => round($netInvesting, 2),
            'net_financing' => round($netFinancing, 2),
            'net_change_in_cash' => round($netChange, 2),
            'beginning_cash' => round($beginningCash, 2),
            'ending_cash' => round($endingCash, 2),
            'calculated_ending' => round($beginningCash + $netChange, 2),
        ];
    }

    /**
     * Generate Journal Entries list report.
     *
     * @param string $fromDate
     * @param string $toDate
     * @param array $filters Additional filters
     * @return Collection
     */
    public function getJournalEntries(string $fromDate, string $toDate, array $filters = []): Collection
    {
        $query = JournalEntry::with(['lines.account', 'creator', 'approver', 'poster', 'accountingPeriod'])
            ->whereBetween('entry_date', [$fromDate, $toDate]);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (!empty($filters['is_manual'])) {
            $query->where('is_manual', $filters['is_manual'] === 'true' || $filters['is_manual'] === '1');
        }

        if (!empty($filters['account_id'])) {
            $query->whereHas('lines', function ($q) use ($filters) {
                $q->where('account_id', $filters['account_id']);
            });
        }

        if (!empty($filters['min_amount'])) {
            $query->whereHas('lines', function ($q) use ($filters) {
                $q->where('debit', '>=', $filters['min_amount'])
                    ->orWhere('credit', '>=', $filters['min_amount']);
            });
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('entry_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('entry_date', 'desc')
            ->orderBy('entry_number', 'desc')
            ->get();
    }

    /**
     * Get daily journal audit summary.
     *
     * @param string $date
     * @return array
     */
    public function getDailyJournalAudit(string $date): array
    {
        $entries = JournalEntry::with(['lines', 'creator'])
            ->where('entry_date', $date)
            ->get();

        $byStatus = $entries->groupBy('status');
        $bySource = $entries->groupBy('source_type_label');

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($entries as $entry) {
            $totalDebit += $entry->total_debit;
            $totalCredit += $entry->total_credit;
        }

        return [
            'date' => $date,
            'generated_at' => now()->toIso8601String(),
            'total_entries' => $entries->count(),
            'by_status' => $byStatus->map->count(),
            'by_source' => $bySource->map->count(),
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'entries' => $entries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'entry_number' => $entry->entry_number,
                    'description' => $entry->description,
                    'status' => $entry->status,
                    'source' => $entry->source_type_label,
                    'debit' => $entry->total_debit,
                    'credit' => $entry->total_credit,
                    'created_by' => $entry->creator->name ?? '',
                ];
            }),
        ];
    }

    /**
     * Get pending entries awaiting approval.
     *
     * @return Collection
     */
    public function getPendingApprovals(): Collection
    {
        return JournalEntry::with(['creator', 'lines', 'accountingPeriod'])
            ->pending()
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * Generate Aged Receivables Report.
     *
     * @param string $asOfDate
     * @return array
     */
    public function getAgedReceivables(string $asOfDate): array
    {
        // Get accounts receivable account group
        $receivablesGroup = AccountGroup::where('name', 'like', '%Receivable%')
            ->orWhere('name', 'like', '%receivable%')
            ->first();

        $accounts = [];
        if ($receivablesGroup) {
            $accounts = Account::where('account_group_id', $receivablesGroup->id)
                ->active()
                ->with(['journalLines' => function ($q) use ($asOfDate) {
                    $q->whereHas('journalEntry', function ($je) use ($asOfDate) {
                        $je->where('entry_date', '<=', $asOfDate)
                            ->where('status', 'posted');
                    });
                }])
                ->get();
        }

        $agingBuckets = [
            'current' => 0,
            '1_30' => 0,
            '31_60' => 0,
            '61_90' => 0,
            'over_90' => 0,
        ];

        $details = [];
        $asOf = Carbon::parse($asOfDate);

        foreach ($accounts as $account) {
            $balance = $account->getBalance(null, $asOfDate);
            if (abs($balance) > 0.01) {
                // Simplified aging - in a real system, this would be based on invoice dates
                $agingBuckets['current'] += $balance;
                $details[] = [
                    'account' => $account,
                    'balance' => $balance,
                    'current' => $balance,
                    '1_30' => 0,
                    '31_60' => 0,
                    '61_90' => 0,
                    'over_90' => 0,
                ];
            }
        }

        return [
            'as_of_date' => $asOfDate,
            'totals' => $agingBuckets,
            'total' => array_sum($agingBuckets),
            'details' => $details,
        ];
    }

    /**
     * Generate Aged Payables Report.
     *
     * @param string $asOfDate
     * @return array
     */
    public function getAgedPayables(string $asOfDate): array
    {
        // Get accounts payable account group
        $payablesGroup = AccountGroup::where('name', 'like', '%Payable%')
            ->orWhere('name', 'like', '%payable%')
            ->first();

        $accounts = [];
        if ($payablesGroup) {
            $accounts = Account::where('account_group_id', $payablesGroup->id)
                ->active()
                ->with(['journalLines' => function ($q) use ($asOfDate) {
                    $q->whereHas('journalEntry', function ($je) use ($asOfDate) {
                        $je->where('entry_date', '<=', $asOfDate)
                            ->where('status', 'posted');
                    });
                }])
                ->get();
        }

        $agingBuckets = [
            'current' => 0,
            '1_30' => 0,
            '31_60' => 0,
            '61_90' => 0,
            'over_90' => 0,
        ];

        $details = [];

        foreach ($accounts as $account) {
            $balance = $account->getBalance(null, $asOfDate);
            if (abs($balance) > 0.01) {
                // Simplified aging - in a real system, this would be based on invoice dates
                $agingBuckets['current'] += $balance;
                $details[] = [
                    'account' => $account,
                    'balance' => $balance,
                    'current' => $balance,
                    '1_30' => 0,
                    '31_60' => 0,
                    '61_90' => 0,
                    'over_90' => 0,
                ];
            }
        }

        return [
            'as_of_date' => $asOfDate,
            'totals' => $agingBuckets,
            'total' => array_sum($agingBuckets),
            'details' => $details,
        ];
    }

    // =========================================
    // HELPER METHODS
    // =========================================

    /**
     * Get accounts with balances for an account class.
     */
    protected function getClassAccountsWithBalances(?AccountClass $class, ?string $fromDate, ?string $toDate): array
    {
        if (!$class) {
            return ['groups' => [], 'accounts' => []];
        }

        $groups = AccountGroup::with(['accounts' => function ($q) {
            $q->active()->orderBy('code');
        }])
            ->where('account_class_id', $class->id)
            ->orderBy('display_order')
            ->get();

        $accountsData = [];
        $groupsData = [];

        foreach ($groups as $group) {
            $groupAccounts = [];
            $groupTotal = 0;

            foreach ($group->accounts as $account) {
                $balance = $account->getBalance($fromDate, $toDate);

                if (abs($balance) < 0.01) {
                    continue;
                }

                $groupAccounts[] = [
                    'id' => $account->id,
                    'code' => $account->full_code,
                    'name' => $account->name,
                    'balance' => round($balance, 2),
                ];

                $accountsData[] = [
                    'id' => $account->id,
                    'code' => $account->full_code,
                    'name' => $account->name,
                    'group' => $group->name,
                    'balance' => round($balance, 2),
                ];

                $groupTotal += $balance;
            }

            if (!empty($groupAccounts)) {
                $groupsData[] = [
                    'name' => $group->name,
                    'accounts' => $groupAccounts,
                    'total' => round($groupTotal, 2),
                ];
            }
        }

        return [
            'class_name' => $class->name,
            'groups' => $groupsData,
            'accounts' => $accountsData,
            'total' => round(collect($accountsData)->sum('balance'), 2),
        ];
    }

    /**
     * Calculate YTD net income.
     */
    protected function calculateYTDNetIncome(string $asOfDate): float
    {
        $year = date('Y', strtotime($asOfDate));
        $startOfYear = "{$year}-01-01";

        $plReport = $this->generateProfitAndLoss($startOfYear, $asOfDate);
        return $plReport['net_income'];
    }

    /**
     * Get cash flow items by category.
     */
    protected function getCashFlowByCategory(string $category, string $fromDate, string $toDate): array
    {
        $accounts = Account::whereHas('accountGroup.accountClass', function ($q) use ($category) {
            $q->where('cash_flow_category', $category);
        })->get();

        $items = [];
        foreach ($accounts as $account) {
            $debit = $account->getDebitTotal($fromDate, $toDate);
            $credit = $account->getCreditTotal($fromDate, $toDate);
            $netChange = $debit - $credit;

            if (abs($netChange) > 0.01) {
                $items[] = [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'amount' => round($netChange, 2),
                ];
            }
        }

        return $items;
    }
}
