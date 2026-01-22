<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Expense Controller
 *
 * Plan Reference: Phase 4 - Controllers
 * Purpose: Manages expense records for the hospital
 *
 * Features:
 * - CRUD operations for expenses
 * - Approval workflow
 * - Category filtering
 * - Reports and analytics
 */
class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:expenses.view')->only(['index', 'show']);
        $this->middleware('permission:expenses.create')->only(['create', 'store']);
        $this->middleware('permission:expenses.edit')->only(['edit', 'update']);
        $this->middleware('permission:expenses.approve')->only(['approve', 'reject']);
        $this->middleware('permission:expenses.void')->only(['void']);
    }

    /**
     * Display a listing of expenses
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Expense::with(['createdBy', 'approvedBy', 'reference'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('expense_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('expense_date', '<=', $request->date_to);
            }

            return DataTables::of($query)
                ->addColumn('expense_date_formatted', function ($expense) {
                    return $expense->expense_date->format('M d, Y');
                })
                ->addColumn('amount_formatted', function ($expense) {
                    return '₦' . number_format($expense->amount, 2);
                })
                ->addColumn('category_badge', function ($expense) {
                    $colors = [
                        'purchase_order' => 'primary',
                        'supplies' => 'info',
                        'utilities' => 'warning',
                        'salaries' => 'success',
                        'maintenance' => 'secondary',
                        'other' => 'dark',
                    ];
                    $color = $colors[$expense->category] ?? 'secondary';
                    return '<span class="badge badge-' . $color . '">' . ucfirst(str_replace('_', ' ', $expense->category)) . '</span>';
                })
                ->addColumn('status_badge', function ($expense) {
                    $colors = [
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'voided' => 'secondary',
                    ];
                    $color = $colors[$expense->status] ?? 'secondary';
                    return '<span class="badge badge-' . $color . '">' . ucfirst($expense->status) . '</span>';
                })
                ->addColumn('created_by_name', function ($expense) {
                    return $expense->createdBy->name ?? 'N/A';
                })
                ->addColumn('reference_info', function ($expense) {
                    if ($expense->reference) {
                        $type = class_basename($expense->reference_type);
                        if ($type === 'PurchaseOrder') {
                            return $expense->reference->po_number ?? 'PO';
                        }
                        return $type;
                    }
                    return '-';
                })
                ->addColumn('actions', function ($expense) {
                    $actions = '<a href="' . route('inventory.expenses.show', $expense) . '" class="btn btn-sm btn-info" title="View"><i class="mdi mdi-eye"></i></a> ';

                    if ($expense->status === 'pending') {
                        if (auth()->user()->can('expenses.edit')) {
                            $actions .= '<a href="' . route('inventory.expenses.edit', $expense) . '" class="btn btn-sm btn-warning" title="Edit"><i class="mdi mdi-pencil"></i></a> ';
                        }
                        if (auth()->user()->can('expenses.approve')) {
                            $actions .= '<button type="button" class="btn btn-sm btn-success" onclick="approveExpense(' . $expense->id . ')" title="Approve"><i class="mdi mdi-check"></i></button> ';
                            $actions .= '<button type="button" class="btn btn-sm btn-danger" onclick="rejectExpense(' . $expense->id . ')" title="Reject"><i class="mdi mdi-close"></i></button> ';
                        }
                    }

                    if ($expense->status === 'approved' && auth()->user()->can('expenses.void')) {
                        $actions .= '<button type="button" class="btn btn-sm btn-secondary" onclick="voidExpense(' . $expense->id . ')" title="Void"><i class="mdi mdi-cancel"></i></button> ';
                    }

                    return $actions;
                })
                ->rawColumns(['category_badge', 'status_badge', 'actions'])
                ->make(true);
        }

        // Stats for dashboard
        $stats = [
            'total' => Expense::count(),
            'pending' => Expense::where('status', 'pending')->count(),
            'approved_this_month' => Expense::where('status', 'approved')
                ->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount'),
            'by_category' => Expense::where('status', 'approved')
                ->whereMonth('expense_date', now()->month)
                ->groupBy('category')
                ->selectRaw('category, SUM(amount) as total')
                ->pluck('total', 'category'),
        ];

        $categories = [
            'purchase_order' => 'Purchase Order',
            'supplies' => 'Supplies',
            'utilities' => 'Utilities',
            'salaries' => 'Salaries',
            'maintenance' => 'Maintenance',
            'other' => 'Other',
        ];

        return view('admin.inventory.expenses.index', compact('stats', 'categories'));
    }

    /**
     * Show the form for creating a new expense
     */
    public function create()
    {
        $categories = [
            'supplies' => 'Supplies',
            'utilities' => 'Utilities',
            'salaries' => 'Salaries',
            'maintenance' => 'Maintenance',
            'other' => 'Other',
        ];

        return view('admin.inventory.expenses.create', compact('categories'));
    }

    /**
     * Store a newly created expense
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0',
            'category' => 'required|string',
            'expense_date' => 'required|date',
            'vendor' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $expense = new Expense();
        $expense->fill($validated);
        $expense->status = 'pending';
        $expense->created_by = Auth::id();
        $expense->save();

        return redirect()->route('inventory.expenses.show', $expense)
            ->with('success', 'Expense created successfully');
    }

    /**
     * Display the specified expense
     */
    public function show(Expense $expense)
    {
        $expense->load(['createdBy', 'approvedBy', 'reference']);

        return view('admin.inventory.expenses.show', compact('expense'));
    }

    /**
     * Show the form for editing the specified expense
     */
    public function edit(Expense $expense)
    {
        if ($expense->status !== 'pending') {
            return back()->with('error', 'Only pending expenses can be edited');
        }

        $categories = [
            'supplies' => 'Supplies',
            'utilities' => 'Utilities',
            'salaries' => 'Salaries',
            'maintenance' => 'Maintenance',
            'other' => 'Other',
        ];

        return view('admin.inventory.expenses.edit', compact('expense', 'categories'));
    }

    /**
     * Update the specified expense
     */
    public function update(Request $request, Expense $expense)
    {
        if ($expense->status !== 'pending') {
            return back()->with('error', 'Only pending expenses can be edited');
        }

        $validated = $request->validate([
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0',
            'category' => 'required|string',
            'expense_date' => 'required|date',
            'vendor' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $expense->update($validated);

        return redirect()->route('inventory.expenses.show', $expense)
            ->with('success', 'Expense updated successfully');
    }

    /**
     * Approve an expense
     */
    public function approve(Request $request, Expense $expense)
    {
        if ($expense->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending expenses can be approved'
            ], 422);
        }

        $expense->status = 'approved';
        $expense->approved_by = Auth::id();
        $expense->approved_at = now();
        $expense->save();

        return response()->json([
            'success' => true,
            'message' => 'Expense approved successfully'
        ]);
    }

    /**
     * Reject an expense
     */
    public function reject(Request $request, Expense $expense)
    {
        if ($expense->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending expenses can be rejected'
            ], 422);
        }

        $expense->status = 'rejected';
        $expense->rejection_reason = $request->rejection_reason;
        $expense->approved_by = Auth::id(); // Track who rejected
        $expense->approved_at = now();
        $expense->save();

        return response()->json([
            'success' => true,
            'message' => 'Expense rejected'
        ]);
    }

    /**
     * Void an approved expense
     */
    public function void(Request $request, Expense $expense)
    {
        if ($expense->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only approved expenses can be voided'
            ], 422);
        }

        $expense->status = 'voided';
        $expense->voided_at = now();
        $expense->voided_by = Auth::id();
        $expense->void_reason = $request->reason;
        $expense->save();

        return response()->json([
            'success' => true,
            'message' => 'Expense voided'
        ]);
    }

    /**
     * Generate expense summary report
     */
    public function summaryReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $expenses = Expense::where('status', 'approved')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        $byCategory = $expenses->groupBy('category')->map(function ($items) {
            return [
                'count' => $items->count(),
                'total' => $items->sum('amount'),
            ];
        });

        $byMonth = $expenses->groupBy(function ($expense) {
            return $expense->expense_date->format('Y-m');
        })->map(function ($items) {
            return $items->sum('amount');
        });

        $stats = [
            'total_expenses' => $expenses->sum('amount'),
            'total_count' => $expenses->count(),
            'average_expense' => $expenses->count() > 0 ? $expenses->avg('amount') : 0,
            'by_category' => $byCategory,
            'by_month' => $byMonth,
        ];

        return view('admin.inventory.expenses.summary-report', compact('stats', 'startDate', 'endDate'));
    }
}
