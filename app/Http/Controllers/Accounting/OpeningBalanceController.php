<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountClass;
use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\FiscalPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

/**
 * Opening Balance Controller
 *
 * Reference: Accounting System Plan §7.5 - Opening Balances
 *
 * Manages opening balances for accounts at the start of a fiscal year.
 */
class OpeningBalanceController extends Controller
{
    public function __construct()
    {
        // Permission middleware (§7.6 - Access Control)
        $this->middleware('permission:accounting.opening-balances.view')->only(['index', 'datatable']);
        $this->middleware('permission:accounting.opening-balances.create')->only(['create', 'store', 'update']);
    }

    /**
     * Display opening balances index.
     */
    public function index(Request $request)
    {
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
        $selectedYear = $request->get('fiscal_year_id', FiscalYear::where('status', 'open')->first()?->id);

        $fiscalYear = $selectedYear ? FiscalYear::find($selectedYear) : null;

        // Get accounts with opening balances grouped by class
        $classes = AccountClass::with([
            'accountGroups.accounts' => function ($query) use ($selectedYear) {
                $query->orderBy('code');
            }
        ])
        ->orderBy('code')
        ->get();

        // Calculate totals
        $stats = [
            'total_debit' => 0,
            'total_credit' => 0,
            'accounts_with_opening' => 0,
            'total_accounts' => Account::where('is_active', true)->count(),
            'balance_difference' => 0,
        ];

        // Count accounts with opening balance in selected year
        if ($fiscalYear) {
            $openingEntries = JournalEntry::where('entry_type', 'opening')
                ->whereDate('entry_date', $fiscalYear->start_date)
                ->where('status', 'posted')
                ->pluck('id');

            $stats['accounts_with_opening'] = JournalLine::whereIn('journal_entry_id', $openingEntries)
                ->distinct('account_id')
                ->count('account_id');

            $debitSum = JournalLine::whereIn('journal_entry_id', $openingEntries)
                ->sum('debit_amount');
            $creditSum = JournalLine::whereIn('journal_entry_id', $openingEntries)
                ->sum('credit_amount');

            $stats['total_debit'] = $debitSum;
            $stats['total_credit'] = $creditSum;
            $stats['balance_difference'] = abs($debitSum - $creditSum);
        }

        return view('accounting.opening-balances.index', compact(
            'classes',
            'fiscalYears',
            'selectedYear',
            'fiscalYear',
            'stats'
        ));
    }

    /**
     * DataTable server-side processing for opening balances.
     */
    public function datatable(Request $request)
    {
        $fiscalYearId = $request->get('fiscal_year_id');
        $fiscalYear = FiscalYear::find($fiscalYearId);

        $query = Account::with(['accountGroup.accountClass'])
            ->where('is_active', true)
            ->select('accounts.*');

        return DataTables::eloquent($query)
            ->filter(function ($query) use ($request) {
                // Class filter
                if ($request->filled('class_id')) {
                    $query->whereHas('accountGroup', function ($q) use ($request) {
                        $q->where('account_class_id', $request->class_id);
                    });
                }

                // Group filter
                if ($request->filled('group_id')) {
                    $query->where('account_group_id', $request->group_id);
                }

                // Has balance filter
                if ($request->filled('has_balance')) {
                    if ($request->has_balance === '1') {
                        $query->where('opening_balance', '!=', 0);
                    } else {
                        $query->where(function($q) {
                            $q->whereNull('opening_balance')
                              ->orWhere('opening_balance', 0);
                        });
                    }
                }

                // Search
                if ($request->filled('search.value')) {
                    $search = $request->input('search.value');
                    $query->where(function ($q) use ($search) {
                        $q->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
                }
            })
            ->addColumn('class_name', function ($row) {
                return $row->accountGroup?->accountClass?->name ?? '-';
            })
            ->addColumn('group_name', function ($row) {
                return $row->accountGroup?->name ?? '-';
            })
            ->addColumn('normal_balance', function ($row) {
                $class = $row->accountGroup?->accountClass;
                if (!$class) return '-';
                return $class->normal_balance === 'debit'
                    ? '<span class="badge badge-info">Debit</span>'
                    : '<span class="badge badge-warning">Credit</span>';
            })
            ->addColumn('opening_balance_formatted', function ($row) {
                $balance = $row->opening_balance ?? 0;
                if ($balance == 0) {
                    return '<span class="text-muted">0.00</span>';
                }
                return number_format($balance, 2);
            })
            ->addColumn('actions', function ($row) {
                return '<button type="button" class="btn btn-sm btn-outline-primary edit-balance"
                            data-account-id="' . $row->id . '"
                            data-account-code="' . $row->account_code . '"
                            data-account-name="' . e($row->name) . '"
                            data-balance="' . ($row->opening_balance ?? 0) . '"
                            data-normal-balance="' . ($row->accountGroup?->accountClass?->normal_balance ?? 'debit') . '">
                            <i class="mdi mdi-pencil"></i> Edit
                        </button>';
            })
            ->rawColumns(['normal_balance', 'opening_balance_formatted', 'actions'])
            ->make(true);
    }

    /**
     * Show the form for creating opening balance entry.
     */
    public function create(Request $request)
    {
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
        $selectedYear = $request->get('fiscal_year_id', FiscalYear::where('status', 'open')->first()?->id);

        $classes = AccountClass::orderBy('code')->get();

        // Get accounts grouped by class for the form
        $accounts = Account::with(['accountGroup.accountClass'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->groupBy(function($account) {
                return $account->accountGroup?->accountClass?->name ?? 'Other';
            });

        return view('accounting.opening-balances.create', compact(
            'fiscalYears',
            'selectedYear',
            'classes',
            'accounts'
        ));
    }

    /**
     * Store opening balances (creates journal entry).
     */
    public function store(Request $request)
    {
        $request->validate([
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
            'balances' => 'required|array',
            'balances.*.account_id' => 'required|exists:accounts,id',
            'balances.*.amount' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            $fiscalYear = FiscalYear::findOrFail($request->fiscal_year_id);

            // Get first period of fiscal year
            $firstPeriod = FiscalPeriod::where('fiscal_year_id', $fiscalYear->id)
                ->orderBy('start_date')
                ->first();

            if (!$firstPeriod) {
                return response()->json([
                    'success' => false,
                    'message' => 'No fiscal period found for this fiscal year'
                ], 422);
            }

            // Create opening balance journal entry
            $journalEntry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $fiscalYear->start_date,
                'fiscal_period_id' => $firstPeriod->id,
                'entry_type' => 'opening',
                'description' => 'Opening Balances for ' . $fiscalYear->year_name,
                'reference' => 'OPENING-' . $fiscalYear->year_name,
                'status' => 'posted',
                'created_by' => Auth::id(),
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ]);

            $totalDebit = 0;
            $totalCredit = 0;
            $lineNumber = 1;

            foreach ($request->balances as $balance) {
                if ($balance['amount'] == 0) continue;

                $account = Account::with('accountGroup.accountClass')->find($balance['account_id']);
                $normalBalance = $account->accountGroup?->accountClass?->normal_balance ?? 'debit';

                $debit = 0;
                $credit = 0;

                if ($balance['amount'] > 0) {
                    if ($normalBalance === 'debit') {
                        $debit = $balance['amount'];
                    } else {
                        $credit = $balance['amount'];
                    }
                } else {
                    // Negative amount goes to contra side
                    if ($normalBalance === 'debit') {
                        $credit = abs($balance['amount']);
                    } else {
                        $debit = abs($balance['amount']);
                    }
                }

                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number' => $lineNumber++,
                    'account_id' => $account->id,
                    'debit' => $debit,
                    'credit' => $credit,
                    'narration' => 'Opening balance',
                ]);

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            // Check balance
            if (abs($totalDebit - $totalCredit) > 0.01) {
                // Add difference to retained earnings or suspense account
                $difference = $totalDebit - $totalCredit;

                // Find or create a suspense/adjustment account
                $adjustmentAccount = Account::where('code', 'like', '3%')
                    ->where('name', 'like', '%Retained%')
                    ->first();

                if (!$adjustmentAccount) {
                    $adjustmentAccount = Account::where('is_active', true)
                        ->whereHas('accountGroup.accountClass', function($q) {
                            $q->where('code', '3'); // Equity
                        })
                        ->first();
                }

                if ($adjustmentAccount) {
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'line_number' => $lineNumber++,
                        'account_id' => $adjustmentAccount->id,
                        'debit' => $difference < 0 ? abs($difference) : 0,
                        'credit' => $difference > 0 ? $difference : 0,
                        'narration' => 'Opening balance adjustment',
                    ]);
                }
            }

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Opening balances saved successfully',
                    'journal_entry_id' => $journalEntry->id
                ]);
            }

            return redirect()->route('accounting.opening-balances.index', ['fiscal_year_id' => $fiscalYear->id])
                ->with('success', 'Opening balances saved successfully');

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error saving opening balances: ' . $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Error saving opening balances: ' . $e->getMessage());
        }
    }

    /**
     * Update single account opening balance.
     */
    public function update(Request $request, $accountId)
    {
        $request->validate([
            'amount' => 'required|numeric',
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
        ]);

        try {
            $account = Account::findOrFail($accountId);
            $fiscalYear = FiscalYear::findOrFail($request->fiscal_year_id);

            $account->update([
                'opening_balance' => $request->amount,
                'opening_balance_date' => $fiscalYear->start_date,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Opening balance updated for ' . $account->name,
                'new_balance' => number_format($request->amount, 2)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating opening balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get accounts JSON for AJAX.
     */
    public function getAccounts(Request $request)
    {
        $classId = $request->get('class_id');
        $groupId = $request->get('group_id');

        $query = Account::with(['accountGroup.accountClass'])
            ->where('is_active', true);

        if ($classId) {
            $query->whereHas('accountGroup', function($q) use ($classId) {
                $q->where('account_class_id', $classId);
            });
        }

        if ($groupId) {
            $query->where('account_group_id', $groupId);
        }

        $accounts = $query->orderBy('account_code')->get();

        return response()->json([
            'success' => true,
            'accounts' => $accounts->map(function($account) {
                return [
                    'id' => $account->id,
                    'account_code' => $account->account_code,
                    'name' => $account->name,
                    'full_name' => $account->account_code . ' - ' . $account->name,
                    'opening_balance' => $account->opening_balance ?? 0,
                    'normal_balance' => $account->accountGroup?->accountClass?->normal_balance ?? 'debit',
                ];
            })
        ]);
    }
}
