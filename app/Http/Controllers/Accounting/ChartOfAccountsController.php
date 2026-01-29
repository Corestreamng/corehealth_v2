<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountClass;
use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Chart of Accounts Controller
 *
 * Reference: Accounting System Plan §7.3 - Controllers
 *
 * Manages account classes, groups, and individual accounts.
 */
class ChartOfAccountsController extends Controller
{
    public function __construct()
    {
        // Permission middleware (§7.6 - Access Control)
        $this->middleware('permission:accounts.view')->only(['index', 'show', 'datatable', 'subAccounts']);
        $this->middleware('permission:accounts.create')->only(['create', 'store', 'createGroup', 'storeGroup', 'storeSubAccount']);
        $this->middleware('permission:accounts.edit')->only(['edit', 'update', 'updateGroup', 'activate', 'deactivate']);
    }

    /**
     * Display the chart of accounts.
     */
    public function index(Request $request)
    {
        $classes = AccountClass::with([
            'groups.accounts' => function ($query) {
                $query->orderBy('code');
            }
        ])
        ->orderBy('code')
        ->get();

        // Stats for cards
        $stats = [
            'total_accounts' => Account::count(),
            'active_accounts' => Account::where('is_active', true)->count(),
            'inactive_accounts' => Account::where('is_active', false)->count(),
            'bank_accounts' => Account::where('is_bank_account', true)->count(),
            'total_classes' => AccountClass::count(),
            'total_groups' => AccountGroup::count(),
        ];

        return view('accounting.chart-of-accounts.index', compact('classes', 'stats'));
    }

    /**
     * DataTable server-side processing for accounts.
     * Reference: §8.2 - Server-Side DataTables
     */
    public function datatable(Request $request)
    {
        $query = Account::with(['accountGroup.accountClass'])
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

                // Status filter
                if ($request->filled('status')) {
                    $query->where('is_active', $request->status === 'active');
                }

                // Bank account filter
                if ($request->filled('is_bank')) {
                    $query->where('is_bank_account', $request->is_bank === '1');
                }

                // Search
                if ($request->filled('search.value')) {
                    $search = $request->input('search.value');
                    $query->where(function ($q) use ($search) {
                        $q->where('account_code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            })
            ->addColumn('account_link', function ($row) {
                $code = '<code>' . e($row->account_code) . '</code>';
                return '<a href="' . route('accounting.chart-of-accounts.show', $row->id) . '">' . $code . '</a>';
            })
            ->addColumn('account_name', function ($row) {
                $html = '<strong>' . e($row->name) . '</strong>';
                if ($row->description) {
                    $html .= '<br><small class="text-muted">' . e(\Str::limit($row->description, 50)) . '</small>';
                }
                return $html;
            })
            ->addColumn('class_name', function ($row) {
                return $row->accountGroup->accountClass->name ?? '-';
            })
            ->addColumn('group_name', function ($row) {
                return $row->accountGroup->name ?? '-';
            })
            ->addColumn('balance_badge', function ($row) {
                $color = $row->normal_balance === 'debit' ? 'info' : 'warning';
                return '<span class="badge badge-' . $color . '">' . ucfirst($row->normal_balance) . '</span>';
            })
            ->addColumn('type_badge', function ($row) {
                if ($row->is_bank_account) {
                    return '<span class="badge badge-success"><i class="mdi mdi-bank mr-1"></i>Bank</span>';
                }
                return '<span class="text-muted">-</span>';
            })
            ->addColumn('status_badge', function ($row) {
                if ($row->is_active) {
                    return '<span class="badge badge-success">Active</span>';
                }
                return '<span class="badge badge-secondary">Inactive</span>';
            })
            ->addColumn('actions', function ($row) {
                $html = '<div class="dropdown">';
                $html .= '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-toggle="dropdown">Actions</button>';
                $html .= '<div class="dropdown-menu dropdown-menu-right">';
                $html .= '<a class="dropdown-item" href="' . route('accounting.chart-of-accounts.show', $row->id) . '"><i class="mdi mdi-eye mr-2"></i>View</a>';
                $html .= '<a class="dropdown-item" href="' . route('accounting.chart-of-accounts.edit', $row->id) . '"><i class="mdi mdi-pencil mr-2"></i>Edit</a>';
                $html .= '<a class="dropdown-item" href="' . route('accounting.chart-of-accounts.sub-accounts', $row->id) . '"><i class="mdi mdi-format-list-bulleted mr-2"></i>Sub-Accounts</a>';
                $html .= '<div class="dropdown-divider"></div>';

                if ($row->is_active) {
                    $html .= '<a class="dropdown-item text-danger btn-deactivate" href="#" data-id="' . $row->id . '"><i class="mdi mdi-close-circle mr-2"></i>Deactivate</a>';
                } else {
                    $html .= '<a class="dropdown-item text-success btn-activate" href="#" data-id="' . $row->id . '"><i class="mdi mdi-check-circle mr-2"></i>Activate</a>';
                }

                $html .= '</div></div>';
                return $html;
            })
            ->rawColumns(['account_link', 'account_name', 'balance_badge', 'type_badge', 'status_badge', 'actions'])
            ->toJson();
    }

    /**
     * Show form for creating a new account.
     */
    public function create()
    {
        $groups = AccountGroup::with('accountClass')
            ->orderBy('code')
            ->get()
            ->groupBy('account_class_id');

        $classes = AccountClass::orderBy('code')->get();

        return view('accounting.chart-of-accounts.create', compact('groups', 'classes'));
    }

    /**
     * Store a new account.
     */
    public function store(Request $request)
    {
        $request->validate([
            'account_group_id' => 'required|exists:account_groups,id',
            'account_code' => 'required|string|max:20|unique:accounts,account_code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'normal_balance' => 'required|in:debit,credit',
            'is_bank_account' => 'boolean',
            'bank_name' => 'nullable|required_if:is_bank_account,1|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
        ]);

        $account = Account::create([
            'account_group_id' => $request->account_group_id,
            'account_code' => $request->account_code,
            'name' => $request->name,
            'description' => $request->description,
            'normal_balance' => $request->normal_balance,
            'is_bank_account' => $request->boolean('is_bank_account'),
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'is_active' => true,
        ]);

        return redirect()->route('accounting.chart-of-accounts.index')
            ->with('success', "Account '{$account->account_code} - {$account->name}' created.");
    }

    /**
     * Display a specific account and its transactions.
     */
    public function show($id)
    {
        $account = Account::with([
            'accountGroup.accountClass',
            'subAccounts',
            'journalEntryLines' => function ($query) {
                $query->with(['journalEntry' => function ($q) {
                    $q->where('status', 'posted');
                }])
                ->orderBy('created_at', 'desc')
                ->limit(50);
            }
        ])->findOrFail($id);

        // Calculate running balance
        $balance = $account->getBalance();
        $periodBalance = $account->getPeriodBalance(now()->startOfMonth(), now());

        return view('accounting.chart-of-accounts.show', compact('account', 'balance', 'periodBalance'));
    }

    /**
     * Show form for editing an account.
     */
    public function edit($id)
    {
        $account = Account::findOrFail($id);

        $groups = AccountGroup::with('accountClass')
            ->orderBy('group_code')
            ->get();

        return view('accounting.chart-of-accounts.edit', compact('account', 'groups'));
    }

    /**
     * Update an account.
     */
    public function update(Request $request, $id)
    {
        $account = Account::findOrFail($id);

        $request->validate([
            'account_group_id' => 'required|exists:account_groups,id',
            'account_code' => "required|string|max:20|unique:accounts,account_code,{$id}",
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'normal_balance' => 'required|in:debit,credit',
            'is_bank_account' => 'boolean',
            'bank_name' => 'nullable|required_if:is_bank_account,1|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $account->update([
            'account_group_id' => $request->account_group_id,
            'account_code' => $request->account_code,
            'name' => $request->name,
            'description' => $request->description,
            'normal_balance' => $request->normal_balance,
            'is_bank_account' => $request->boolean('is_bank_account'),
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('accounting.chart-of-accounts.show', $id)
            ->with('success', 'Account updated successfully.');
    }

    /**
     * Deactivate an account (soft disable).
     */
    public function deactivate(Request $request, $id)
    {
        $account = Account::findOrFail($id);

        // Check if account has non-zero balance
        if ($account->getBalance() != 0) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Cannot deactivate account with non-zero balance.'], 400);
            }
            return redirect()->back()
                ->with('error', 'Cannot deactivate account with non-zero balance.');
        }

        $account->update(['is_active' => false]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => "Account {$account->account_code} deactivated."]);
        }

        return redirect()->back()
            ->with('success', 'Account deactivated.');
    }

    /**
     * Activate an account.
     */
    public function activate(Request $request, $id)
    {
        $account = Account::findOrFail($id);
        $account->update(['is_active' => true]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => "Account {$account->account_code} activated."]);
        }

        return redirect()->back()
            ->with('success', 'Account activated.');
    }

    // ==================== ACCOUNT GROUPS ====================

    /**
     * Show form for creating a new account group.
     */
    public function createGroup()
    {
        $classes = AccountClass::orderBy('code')->get();
        return view('accounting.chart-of-accounts.create-group', compact('classes'));
    }

    /**
     * Store a new account group.
     */
    public function storeGroup(Request $request)
    {
        $request->validate([
            'account_class_id' => 'required|exists:account_classes,id',
            'group_code' => 'required|string|max:10|unique:account_groups,group_code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $group = AccountGroup::create([
            'account_class_id' => $request->account_class_id,
            'group_code' => $request->group_code,
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('accounting.chart-of-accounts.index')
            ->with('success', "Account group '{$group->name}' created.");
    }

    /**
     * Update an account group.
     */
    public function updateGroup(Request $request, $id)
    {
        $group = AccountGroup::findOrFail($id);

        $request->validate([
            'account_class_id' => 'required|exists:account_classes,id',
            'group_code' => "required|string|max:10|unique:account_groups,group_code,{$id}",
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $group->update($request->only(['account_class_id', 'group_code', 'name', 'description']));

        return redirect()->route('accounting.chart-of-accounts.index')
            ->with('success', 'Account group updated.');
    }

    // ==================== SUB-ACCOUNTS ====================

    /**
     * List sub-accounts for an account.
     */
    public function subAccounts($accountId)
    {
        $account = Account::with('subAccounts.entity')->findOrFail($accountId);

        return view('accounting.chart-of-accounts.sub-accounts', compact('account'));
    }

    /**
     * Create sub-account for an account.
     */
    public function storeSubAccount(Request $request, $accountId)
    {
        $account = Account::findOrFail($accountId);

        $request->validate([
            'sub_code' => 'required|string|max:20',
            'name' => 'required|string|max:100',
            'entity_type' => 'nullable|string|max:100',
            'entity_id' => 'nullable|integer',
        ]);

        // Check unique sub_code for this account
        $exists = AccountSubAccount::where('account_id', $accountId)
            ->where('sub_code', $request->sub_code)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'Sub-account code already exists for this account.');
        }

        $subAccount = AccountSubAccount::create([
            'account_id' => $accountId,
            'sub_code' => $request->sub_code,
            'name' => $request->name,
            'entity_type' => $request->entity_type,
            'entity_id' => $request->entity_id,
            'is_active' => true,
        ]);

        return redirect()->back()
            ->with('success', "Sub-account '{$subAccount->name}' created.");
    }

    /**
     * Get accounts as JSON for AJAX requests.
     */
    public function getAccountsJson(Request $request)
    {
        $query = Account::where('is_active', true);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('account_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('class_code')) {
            $query->whereHas('accountGroup.accountClass', function ($q) use ($request) {
                $q->where('code', $request->class_code);
            });
        }

        $accounts = $query->orderBy('account_code')
            ->limit(50)
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'code' => $account->account_code,
                    'name' => $account->name,
                    'full_name' => "{$account->account_code} - {$account->name}",
                    'normal_balance' => $account->normal_balance,
                ];
            });

        return response()->json($accounts);
    }
}
