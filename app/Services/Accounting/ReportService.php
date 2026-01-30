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
     * Generate Comprehensive Aged Receivables Report.
     *
     * Includes:
     * - Patient overdrafts (negative balance = owes hospital)
     * - HMO claims validated but no remittance yet
     * - General Ledger receivables accounts
     *
     * @param string $asOfDate
     * @param array $filters Optional filters: hmo_id, min_amount, receivable_type
     * @return array
     */
    public function getAgedReceivables(string $asOfDate, array $filters = []): array
    {
        $asOf = Carbon::parse($asOfDate);

        $agingBuckets = [
            'current' => 0,
            '1_30' => 0,
            '31_60' => 0,
            '61_90' => 0,
            'over_90' => 0,
        ];

        $categories = [];

        // ========================================
        // 1. Patient Overdrafts (Negative Balance = Patient Owes Hospital)
        // ========================================
        $patientOverdrafts = $this->getPatientOverdrafts($asOf, $filters);
        $categories['patient_overdrafts'] = $patientOverdrafts;

        foreach ($patientOverdrafts['details'] as $item) {
            $agingBuckets[$item['aging_bucket']] += abs($item['amount']);
        }

        // ========================================
        // 2. HMO Claims Validated but No Remittance
        // ========================================
        $hmoClaims = $this->getHmoClaimsPendingRemittance($asOf, $filters);
        $categories['hmo_claims'] = $hmoClaims;

        foreach ($hmoClaims['details'] as $item) {
            $agingBuckets[$item['aging_bucket']] += $item['amount'];
        }

        // ========================================
        // 3. General Ledger Accounts Receivable
        // ========================================
        $glReceivables = $this->getGLReceivables($asOf, $filters);
        $categories['gl_receivables'] = $glReceivables;

        foreach ($glReceivables['details'] as $item) {
            $agingBuckets['current'] += $item['balance'];
        }

        // Calculate grand totals
        $grandTotal = array_sum($agingBuckets);

        return [
            'as_of_date' => $asOfDate,
            'totals' => $agingBuckets,
            'total' => $grandTotal,
            'categories' => $categories,
            'summary' => [
                'patient_overdrafts' => $patientOverdrafts['total'],
                'hmo_claims' => $hmoClaims['total'],
                'gl_receivables' => $glReceivables['total'],
            ],
            // Legacy format for backward compatibility
            'details' => $this->flattenReceivablesDetails($categories),
        ];
    }

    /**
     * Get patient accounts with overdraft (negative balance = patient owes hospital)
     */
    protected function getPatientOverdrafts(Carbon $asOf, array $filters = []): array
    {
        $query = \App\Models\PatientAccount::with(['patient', 'patient.user', 'patient.hmo'])
            ->whereHas('patient') // Ensure patient exists
            ->where('balance', '<', 0); // Negative balance means patient owes

        if (!empty($filters['min_amount'])) {
            $query->where('balance', '<=', -abs($filters['min_amount']));
        }

        $overdrafts = $query->get();

        $details = [];
        $total = 0;

        foreach ($overdrafts as $account) {
            // Skip if patient doesn't exist
            if (!$account->patient) {
                continue;
            }

            $amount = abs($account->balance);
            $total += $amount;

            // Determine aging bucket based on last update or a reasonable estimate
            $ageBucket = $this->determineAgingBucket($account->updated_at ?? $account->created_at, $asOf);

            // Get patient data safely
            $patient = $account->patient;
            $user = $patient->user;
            $hmo = $patient->hmo;

            $details[] = [
                'id' => $account->id,
                'patient_id' => $account->patient_id,
                'patient_name' => $user ? $user->name : 'Unknown Patient',
                'patient_file_no' => $patient->file_no ?? 'N/A',
                'patient_phone' => $patient->phone_no ?? 'N/A',
                'hmo_name' => $hmo ? $hmo->name : 'Self-Pay',
                'amount' => $amount,
                'last_activity' => $account->updated_at ? $account->updated_at->format('Y-m-d') : 'N/A',
                'aging_bucket' => $ageBucket,
                'type' => 'patient_overdraft',
            ];
        }

        return [
            'label' => 'Patient Overdrafts',
            'description' => 'Patients with negative account balance (owes hospital)',
            'total' => $total,
            'count' => count($details),
            'details' => $details,
        ];
    }

    /**
     * Get HMO claims that have been validated but no remittance received
     */
    protected function getHmoClaimsPendingRemittance(Carbon $asOf, array $filters = []): array
    {
        // Claims from ProductOrServiceRequest with validation but no remittance
        $query = \App\Models\ProductOrServiceRequest::with(['payment.patient.user', 'service', 'product'])
            ->whereNotNull('validated_at')
            ->whereNotNull('hmo_id')
            ->whereNull('hmo_remittance_id')
            ->where('validation_status', 'validated')
            ->whereDate('validated_at', '<=', $asOf);

        if (!empty($filters['hmo_id'])) {
            $query->where('hmo_id', $filters['hmo_id']);
        }

        $claims = $query->get();

        // Group by HMO
        $hmoGrouped = $claims->groupBy('hmo_id');

        $details = [];
        $total = 0;
        $hmoSummary = [];

        foreach ($hmoGrouped as $hmoId => $hmoClaims) {
            $hmo = \App\Models\Hmo::find($hmoId);
            $hmoName = $hmo?->name ?? 'Unknown HMO';
            $hmoTotal = 0;
            $claimDetails = [];

            foreach ($hmoClaims as $claim) {
                $claimAmount = $claim->claims_amount ?? $claim->payable_amount ?? 0;
                if ($claimAmount <= 0) continue;

                $hmoTotal += $claimAmount;
                $ageBucket = $this->determineAgingBucket($claim->validated_at, $asOf);

                $claimDetails[] = [
                    'claim_id' => $claim->id,
                    'patient_name' => $claim->payment?->patient?->user?->name ?? 'Unknown',
                    'service_name' => $claim->service?->name ?? $claim->product?->name ?? 'Item',
                    'claim_amount' => $claimAmount,
                    'validated_at' => $claim->validated_at?->format('Y-m-d'),
                    'auth_code' => $claim->auth_code ?? '-',
                    'aging_bucket' => $ageBucket,
                ];
            }

            if ($hmoTotal > 0) {
                $total += $hmoTotal;

                // For aging bucket, use the oldest claim's bucket
                $oldestBucket = 'current';
                foreach ($claimDetails as $cd) {
                    if ($this->isOlderBucket($cd['aging_bucket'], $oldestBucket)) {
                        $oldestBucket = $cd['aging_bucket'];
                    }
                }

                $details[] = [
                    'hmo_id' => $hmoId,
                    'hmo_name' => $hmoName,
                    'amount' => $hmoTotal,
                    'claim_count' => count($claimDetails),
                    'claims' => $claimDetails,
                    'aging_bucket' => $oldestBucket,
                    'type' => 'hmo_claim',
                ];

                $hmoSummary[$hmoId] = [
                    'name' => $hmoName,
                    'amount' => $hmoTotal,
                    'count' => count($claimDetails),
                ];
            }
        }

        return [
            'label' => 'HMO Claims Pending Remittance',
            'description' => 'Validated HMO claims awaiting payment',
            'total' => $total,
            'count' => count($details),
            'details' => $details,
            'by_hmo' => $hmoSummary,
        ];
    }

    /**
     * Get General Ledger accounts receivable
     */
    protected function getGLReceivables(Carbon $asOf, array $filters = []): array
    {
        $receivablesGroup = AccountGroup::where('name', 'like', '%Receivable%')
            ->orWhere('name', 'like', '%receivable%')
            ->first();

        $details = [];
        $total = 0;

        if ($receivablesGroup) {
            $accounts = Account::where('account_group_id', $receivablesGroup->id)
                ->active()
                ->get();

            foreach ($accounts as $account) {
                $balance = $account->getBalance(null, $asOf->format('Y-m-d'));
                if (abs($balance) > 0.01) {
                    $total += $balance;
                    $details[] = [
                        'account_id' => $account->id,
                        'account_code' => $account->full_code ?? $account->code,
                        'account_name' => $account->name,
                        'balance' => $balance,
                        'type' => 'gl_account',
                    ];
                }
            }
        }

        return [
            'label' => 'GL Accounts Receivable',
            'description' => 'General ledger receivables accounts',
            'total' => $total,
            'count' => count($details),
            'details' => $details,
        ];
    }

    /**
     * Flatten categories for backward compatibility
     */
    protected function flattenReceivablesDetails(array $categories): array
    {
        $flat = [];

        foreach ($categories as $catKey => $category) {
            foreach ($category['details'] ?? [] as $item) {
                $flat[] = array_merge($item, ['category' => $catKey]);
            }
        }

        return $flat;
    }

    /**
     * Generate Comprehensive Aged Payables Report.
     *
     * Includes:
     * - Supplier POs received but not fully paid
     * - Patient deposits (liability - hospital owes patient)
     * - General Ledger payables accounts
     *
     * @param string $asOfDate
     * @param array $filters Optional filters: supplier_id, min_amount, payable_type
     * @return array
     */
    public function getAgedPayables(string $asOfDate, array $filters = []): array
    {
        $asOf = Carbon::parse($asOfDate);

        $agingBuckets = [
            'current' => 0,
            '1_30' => 0,
            '31_60' => 0,
            '61_90' => 0,
            'over_90' => 0,
        ];

        $categories = [];

        // ========================================
        // 1. Supplier Purchase Orders - Received but Not Paid
        // ========================================
        $supplierPayables = $this->getSupplierPayables($asOf, $filters);
        $categories['supplier_payables'] = $supplierPayables;

        foreach ($supplierPayables['details'] as $item) {
            $agingBuckets[$item['aging_bucket']] += $item['outstanding_amount'];
        }

        // ========================================
        // 2. Patient Deposits (Positive Balance = Hospital Owes Patient)
        // ========================================
        $patientDeposits = $this->getPatientDeposits($asOf, $filters);
        $categories['patient_deposits'] = $patientDeposits;

        foreach ($patientDeposits['details'] as $item) {
            $agingBuckets[$item['aging_bucket']] += $item['amount'];
        }

        // ========================================
        // 3. Supplier Credit Balances (from credit field)
        // ========================================
        $supplierCredits = $this->getSupplierCredits($asOf, $filters);
        $categories['supplier_credits'] = $supplierCredits;

        foreach ($supplierCredits['details'] as $item) {
            $agingBuckets['current'] += $item['credit_amount'];
        }

        // ========================================
        // 4. General Ledger Accounts Payable
        // ========================================
        $glPayables = $this->getGLPayables($asOf, $filters);
        $categories['gl_payables'] = $glPayables;

        foreach ($glPayables['details'] as $item) {
            $agingBuckets['current'] += $item['balance'];
        }

        // Calculate grand totals
        $grandTotal = array_sum($agingBuckets);

        // Build payment priority list (oldest/largest first)
        $priorities = $this->buildPaymentPriorities($categories);

        return [
            'as_of_date' => $asOfDate,
            'totals' => $agingBuckets,
            'total' => $grandTotal,
            'categories' => $categories,
            'priorities' => $priorities,
            'summary' => [
                'supplier_payables' => $supplierPayables['total'],
                'patient_deposits' => $patientDeposits['total'],
                'supplier_credits' => $supplierCredits['total'],
                'gl_payables' => $glPayables['total'],
            ],
            // Legacy format for backward compatibility
            'details' => $this->flattenPayablesDetails($categories),
        ];
    }

    /**
     * Get supplier POs that are received but not fully paid
     */
    protected function getSupplierPayables(Carbon $asOf, array $filters = []): array
    {
        $query = \App\Models\PurchaseOrder::with(['supplier', 'items.product'])
            ->whereIn('status', [
                \App\Models\PurchaseOrder::STATUS_PARTIAL,
                \App\Models\PurchaseOrder::STATUS_RECEIVED,
                \App\Models\PurchaseOrder::STATUS_APPROVED,
            ])
            ->whereIn('payment_status', [
                \App\Models\PurchaseOrder::PAYMENT_UNPAID,
                \App\Models\PurchaseOrder::PAYMENT_PARTIAL,
            ])
            ->whereDate('created_at', '<=', $asOf);

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        $orders = $query->get();

        // Group by supplier
        $supplierGrouped = $orders->groupBy('supplier_id');

        $details = [];
        $total = 0;
        $supplierSummary = [];

        foreach ($supplierGrouped as $supplierId => $supplierOrders) {
            $supplier = \App\Models\Supplier::find($supplierId);
            $supplierName = $supplier?->company_name ?? 'Unknown Supplier';
            $supplierTotal = 0;
            $poDetails = [];

            foreach ($supplierOrders as $po) {
                $outstanding = ($po->total_amount ?? 0) - ($po->amount_paid ?? 0);
                if ($outstanding <= 0) continue;

                $supplierTotal += $outstanding;
                $ageBucket = $this->determineAgingBucket($po->approved_at ?? $po->created_at, $asOf);

                $poDetails[] = [
                    'po_id' => $po->id,
                    'po_number' => $po->po_number,
                    'total_amount' => $po->total_amount,
                    'amount_paid' => $po->amount_paid,
                    'outstanding' => $outstanding,
                    'status' => $po->status,
                    'payment_status' => $po->payment_status,
                    'po_date' => $po->created_at?->format('Y-m-d'),
                    'expected_date' => $po->expected_date?->format('Y-m-d'),
                    'aging_bucket' => $ageBucket,
                ];
            }

            if ($supplierTotal > 0) {
                $total += $supplierTotal;

                // Use the oldest PO's aging bucket
                $oldestBucket = 'current';
                foreach ($poDetails as $pd) {
                    if ($this->isOlderBucket($pd['aging_bucket'], $oldestBucket)) {
                        $oldestBucket = $pd['aging_bucket'];
                    }
                }

                $details[] = [
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplierName,
                    'contact_person' => $supplier?->contact_person,
                    'phone' => $supplier?->phone,
                    'email' => $supplier?->email,
                    'outstanding_amount' => $supplierTotal,
                    'po_count' => count($poDetails),
                    'purchase_orders' => $poDetails,
                    'aging_bucket' => $oldestBucket,
                    'type' => 'supplier_po',
                ];

                $supplierSummary[$supplierId] = [
                    'name' => $supplierName,
                    'amount' => $supplierTotal,
                    'count' => count($poDetails),
                ];
            }
        }

        return [
            'label' => 'Supplier Purchase Orders',
            'description' => 'POs received but not fully paid',
            'total' => $total,
            'count' => count($details),
            'details' => $details,
            'by_supplier' => $supplierSummary,
        ];
    }

    /**
     * Get patient accounts with deposits (positive balance = hospital owes patient)
     */
    protected function getPatientDeposits(Carbon $asOf, array $filters = []): array
    {
        $query = \App\Models\PatientAccount::with(['patient', 'patient.user', 'patient.hmo'])
            ->whereHas('patient') // Ensure patient exists
            ->where('balance', '>', 0); // Positive balance means patient has credit/deposit

        if (!empty($filters['min_amount'])) {
            $query->where('balance', '>=', $filters['min_amount']);
        }

        $deposits = $query->get();

        $details = [];
        $total = 0;

        foreach ($deposits as $account) {
            // Skip if patient doesn't exist
            if (!$account->patient) {
                continue;
            }

            $amount = $account->balance;
            $total += $amount;

            // Determine aging bucket based on last update
            $ageBucket = $this->determineAgingBucket($account->updated_at ?? $account->created_at, $asOf);

            // Get patient data safely
            $patient = $account->patient;
            $user = $patient->user;
            $hmo = $patient->hmo;

            $details[] = [
                'id' => $account->id,
                'patient_id' => $account->patient_id,
                'patient_name' => $user ? $user->name : 'Unknown Patient',
                'patient_file_no' => $patient->file_no ?? 'N/A',
                'patient_phone' => $patient->phone_no ?? 'N/A',
                'hmo_name' => $hmo ? $hmo->name : 'Self-Pay',
                'amount' => $amount,
                'last_activity' => $account->updated_at ? $account->updated_at->format('Y-m-d') : 'N/A',
                'aging_bucket' => $ageBucket,
                'type' => 'patient_deposit',
            ];
        }

        return [
            'label' => 'Patient Deposits',
            'description' => 'Unused patient deposits (hospital liability)',
            'total' => $total,
            'count' => count($details),
            'details' => $details,
        ];
    }

    /**
     * Get supplier credit balances
     */
    protected function getSupplierCredits(Carbon $asOf, array $filters = []): array
    {
        $query = \App\Models\Supplier::where('credit', '>', 0);

        if (!empty($filters['supplier_id'])) {
            $query->where('id', $filters['supplier_id']);
        }

        $suppliers = $query->get();

        $details = [];
        $total = 0;

        foreach ($suppliers as $supplier) {
            $credit = $supplier->credit ?? 0;
            if ($credit > 0) {
                $total += $credit;
                $details[] = [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->company_name,
                    'contact_person' => $supplier->contact_person,
                    'phone' => $supplier->phone,
                    'email' => $supplier->email,
                    'credit_amount' => $credit,
                    'credit_limit' => $supplier->credit_limit ?? 0,
                    'type' => 'supplier_credit',
                ];
            }
        }

        return [
            'label' => 'Supplier Credits',
            'description' => 'Credit balances owed to suppliers',
            'total' => $total,
            'count' => count($details),
            'details' => $details,
        ];
    }

    /**
     * Get General Ledger accounts payable
     */
    protected function getGLPayables(Carbon $asOf, array $filters = []): array
    {
        $payablesGroup = AccountGroup::where('name', 'like', '%Payable%')
            ->orWhere('name', 'like', '%payable%')
            ->first();

        $details = [];
        $total = 0;

        if ($payablesGroup) {
            $accounts = Account::where('account_group_id', $payablesGroup->id)
                ->active()
                ->get();

            foreach ($accounts as $account) {
                $balance = $account->getBalance(null, $asOf->format('Y-m-d'));
                if (abs($balance) > 0.01) {
                    $total += abs($balance);
                    $details[] = [
                        'account_id' => $account->id,
                        'account_code' => $account->full_code ?? $account->code,
                        'account_name' => $account->name,
                        'balance' => abs($balance),
                        'type' => 'gl_account',
                    ];
                }
            }
        }

        return [
            'label' => 'GL Accounts Payable',
            'description' => 'General ledger payables accounts',
            'total' => $total,
            'count' => count($details),
            'details' => $details,
        ];
    }

    /**
     * Build payment priority list
     */
    protected function buildPaymentPriorities(array $categories): array
    {
        $priorities = [];

        // Prioritize supplier POs by age and amount
        if (!empty($categories['supplier_payables']['details'])) {
            foreach ($categories['supplier_payables']['details'] as $supplier) {
                foreach ($supplier['purchase_orders'] ?? [] as $po) {
                    $priorities[] = [
                        'type' => 'supplier_po',
                        'vendor_name' => $supplier['supplier_name'],
                        'reference' => $po['po_number'],
                        'amount' => $po['outstanding'],
                        'date' => $po['po_date'],
                        'days_overdue' => $this->calculateDaysInBucket($po['aging_bucket']),
                        'priority_score' => $this->calculatePriorityScore($po['outstanding'], $po['aging_bucket']),
                    ];
                }
            }
        }

        // Sort by priority score (higher = more urgent)
        usort($priorities, fn($a, $b) => $b['priority_score'] <=> $a['priority_score']);

        return array_slice($priorities, 0, 20); // Top 20 priorities
    }

    /**
     * Calculate priority score based on amount and age
     */
    protected function calculatePriorityScore(float $amount, string $ageBucket): float
    {
        $ageMultiplier = match($ageBucket) {
            'over_90' => 5,
            '61_90' => 4,
            '31_60' => 3,
            '1_30' => 2,
            default => 1,
        };

        return ($amount / 1000) * $ageMultiplier;
    }

    /**
     * Calculate approximate days based on aging bucket
     */
    protected function calculateDaysInBucket(string $bucket): int
    {
        return match($bucket) {
            'over_90' => 90,
            '61_90' => 75,
            '31_60' => 45,
            '1_30' => 15,
            default => 0,
        };
    }

    /**
     * Flatten categories for backward compatibility
     */
    protected function flattenPayablesDetails(array $categories): array
    {
        $flat = [];

        foreach ($categories as $catKey => $category) {
            foreach ($category['details'] ?? [] as $item) {
                $flat[] = array_merge($item, ['category' => $catKey]);
            }
        }

        return $flat;
    }

    /**
     * Determine aging bucket based on date
     */
    protected function determineAgingBucket($date, Carbon $asOf): string
    {
        if (!$date) return 'current';

        $itemDate = Carbon::parse($date);
        $daysDiff = $itemDate->diffInDays($asOf);

        if ($daysDiff <= 0) return 'current';
        if ($daysDiff <= 30) return '1_30';
        if ($daysDiff <= 60) return '31_60';
        if ($daysDiff <= 90) return '61_90';
        return 'over_90';
    }

    /**
     * Check if bucket1 is older than bucket2
     */
    protected function isOlderBucket(string $bucket1, string $bucket2): bool
    {
        $order = ['current' => 0, '1_30' => 1, '31_60' => 2, '61_90' => 3, 'over_90' => 4];
        return ($order[$bucket1] ?? 0) > ($order[$bucket2] ?? 0);
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
     *
     * Updated to use line-level cash_flow_category for more accurate classification.
     * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 1B
     */
    protected function getCashFlowByCategory(string $category, string $fromDate, string $toDate): array
    {
        // First, try to get from line-level classification (more accurate)
        $lineItems = $this->getCashFlowFromLines($category, $fromDate, $toDate);

        if (!empty($lineItems)) {
            return $lineItems;
        }

        // Fallback: Use account class-level classification
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

    /**
     * Get cash flow items from journal entry lines with line-level classification.
     *
     * This provides more accurate cash flow reporting because:
     * 1. Each line can have its own cash flow category
     * 2. Categories are auto-classified based on transaction context
     * 3. Overrides at account level are respected
     *
     * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 1B
     */
    protected function getCashFlowFromLines(string $category, string $fromDate, string $toDate): array
    {
        $results = JournalEntryLine::query()
            ->select([
                'journal_entry_lines.category',
                'accounts.id as account_id',
                'accounts.name as account_name',
                DB::raw('SUM(journal_entry_lines.debit) - SUM(journal_entry_lines.credit) as net_change')
            ])
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.entry_date', [$fromDate, $toDate])
            ->where('journal_entry_lines.cash_flow_category', $category)
            ->groupBy('journal_entry_lines.category', 'accounts.id', 'accounts.name')
            ->havingRaw('ABS(SUM(journal_entry_lines.debit) - SUM(journal_entry_lines.credit)) > 0.01')
            ->get();

        $items = [];
        foreach ($results as $result) {
            $items[] = [
                'account_id' => $result->account_id,
                'account_name' => $result->account_name,
                'category' => $result->category,
                'amount' => round($result->net_change, 2),
            ];
        }

        return $items;
    }
}
