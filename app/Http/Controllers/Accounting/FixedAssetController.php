<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetCategory;
use App\Models\Accounting\FixedAssetDepreciation;
use App\Models\Accounting\FixedAssetDisposal;
use App\Models\Accounting\Account;
use App\Models\Bank;
use App\Models\Department;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\FixedAssetService;
use App\Services\Accounting\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Fixed Asset Controller
 *
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
 * Access: SUPERADMIN|ADMIN|ACCOUNTS
 *
 * Manages fixed assets register with IAS 16 compliant depreciation.
 * Key features:
 * - Asset registration (manual and from PO)
 * - Multiple depreciation methods
 * - Depreciation scheduling and execution
 * - Asset disposal with gain/loss calculation
 * - Department transfers
 * - Maintenance tracking
 */
class FixedAssetController extends Controller
{
    protected FixedAssetService $assetService;

    public function __construct(FixedAssetService $assetService)
    {
        $this->assetService = $assetService;
        $this->middleware('role:SUPERADMIN|ADMIN|ACCOUNTS');
    }

    /**
     * Display fixed assets dashboard.
     */
    public function index(Request $request)
    {
        $stats = $this->getDashboardStats();

        $categories = FixedAssetCategory::active()->orderBy('name')->get();

        $statusOptions = [
            FixedAsset::STATUS_ACTIVE => 'Active',
            FixedAsset::STATUS_FULLY_DEPRECIATED => 'Fully Depreciated',
            FixedAsset::STATUS_DISPOSED => 'Disposed',
            FixedAsset::STATUS_IMPAIRED => 'Impaired',
            FixedAsset::STATUS_UNDER_MAINTENANCE => 'Under Maintenance',
            FixedAsset::STATUS_IDLE => 'Idle',
        ];

        $departments = Department::orderBy('name')->get();

        // Banks for disposal payment source selection
        $banks = Bank::with('account')->orderBy('name')->get();

        return view('accounting.fixed-assets.index', compact('stats', 'categories', 'statusOptions', 'departments', 'banks'));
    }

    /**
     * Get dashboard statistics.
     */
    protected function getDashboardStats(): array
    {
        $thisMonth = now()->startOfMonth()->toDateString();
        $thisYear = now()->startOfYear()->toDateString();

        // Active assets
        $totalAssets = FixedAsset::where('status', '!=', FixedAsset::STATUS_DISPOSED)->count();
        $totalCost = FixedAsset::where('status', '!=', FixedAsset::STATUS_DISPOSED)->sum('total_cost');
        $totalBookValue = FixedAsset::where('status', '!=', FixedAsset::STATUS_DISPOSED)->sum('book_value');
        $totalAccumDepreciation = FixedAsset::where('status', '!=', FixedAsset::STATUS_DISPOSED)->sum('accumulated_depreciation');

        // By status
        $activeCount = FixedAsset::where('status', FixedAsset::STATUS_ACTIVE)->count();
        $fullyDepreciatedCount = FixedAsset::where('status', FixedAsset::STATUS_FULLY_DEPRECIATED)->count();
        $disposedCount = FixedAsset::where('status', FixedAsset::STATUS_DISPOSED)->count();

        // Depreciation
        $monthlyDepreciationDue = FixedAsset::where('status', FixedAsset::STATUS_ACTIVE)
            ->whereNotNull('monthly_depreciation')
            ->sum('monthly_depreciation');

        $ytdDepreciation = FixedAssetDepreciation::whereDate('depreciation_date', '>=', $thisYear)->sum('amount');
        $mtdDepreciation = FixedAssetDepreciation::whereDate('depreciation_date', '>=', $thisMonth)->sum('amount');

        // Assets by category
        $byCategory = FixedAsset::where('status', '!=', FixedAsset::STATUS_DISPOSED)
            ->select('category_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(book_value) as value'))
            ->groupBy('category_id')
            ->with('category')
            ->get();

        // Disposals this year
        $ytdDisposals = FixedAssetDisposal::whereDate('disposal_date', '>=', $thisYear)->count();
        $ytdDisposalGainLoss = FixedAssetDisposal::whereDate('disposal_date', '>=', $thisYear)
            ->sum(DB::raw('gain_loss_amount'));

        // Assets needing attention
        $warrantyExpiringSoon = FixedAsset::where('status', FixedAsset::STATUS_ACTIVE)
            ->whereNotNull('warranty_expiry_date')
            ->whereBetween('warranty_expiry_date', [now(), now()->addDays(30)])
            ->count();

        $insuranceExpiringSoon = FixedAsset::where('status', FixedAsset::STATUS_ACTIVE)
            ->whereNotNull('insurance_expiry_date')
            ->whereBetween('insurance_expiry_date', [now(), now()->addDays(30)])
            ->count();

        return [
            'total_assets' => $totalAssets,
            'total_cost' => $totalCost,
            'total_book_value' => $totalBookValue,
            'total_accum_depreciation' => $totalAccumDepreciation,
            'active_count' => $activeCount,
            'fully_depreciated_count' => $fullyDepreciatedCount,
            'disposed_count' => $disposedCount,
            'monthly_depreciation_due' => $monthlyDepreciationDue,
            'ytd_depreciation' => $ytdDepreciation,
            'mtd_depreciation' => $mtdDepreciation,
            'by_category' => $byCategory,
            'ytd_disposals' => $ytdDisposals,
            'ytd_disposal_gain_loss' => $ytdDisposalGainLoss,
            'warranty_expiring_soon' => $warrantyExpiringSoon,
            'insurance_expiring_soon' => $insuranceExpiringSoon,
        ];
    }

    /**
     * DataTables server-side processing for assets.
     */
    public function datatable(Request $request)
    {
        $query = FixedAsset::with(['category', 'department', 'custodian', 'supplier'])
            ->select('fixed_assets.*');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('acquisition_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('acquisition_date', '<=', $request->date_to);
        }

        if ($request->filled('search_term')) {
            $term = $request->search_term;
            $query->where(function ($q) use ($term) {
                $q->where('asset_number', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('serial_number', 'like', "%{$term}%")
                    ->orWhere('model_number', 'like', "%{$term}%");
            });
        }

        return DataTables::of($query)
            ->addColumn('category_name', fn($a) => $a->category?->name ?? 'N/A')
            ->addColumn('department_name', fn($a) => $a->department?->name ?? 'N/A')
            ->addColumn('depreciation_percent', function ($asset) {
                if ($asset->total_cost <= 0) return 0;
                return round(($asset->accumulated_depreciation / $asset->total_cost) * 100, 1);
            })
            ->addColumn('status_badge', function ($a) {
                $colors = [
                    'active' => 'success',
                    'fully_depreciated' => 'info',
                    'disposed' => 'secondary',
                    'impaired' => 'warning',
                    'under_maintenance' => 'primary',
                    'idle' => 'dark',
                ];
                return '<span class="badge badge-' . ($colors[$a->status] ?? 'secondary') . '">'
                    . ucfirst(str_replace('_', ' ', $a->status)) . '</span>';
            })
            ->addColumn('actions', function ($a) {
                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . route('accounting.fixed-assets.show', $a) . '" class="btn btn-info" title="View"><i class="mdi mdi-eye"></i></a>';
                $actions .= '<a href="' . route('accounting.fixed-assets.edit', $a) . '" class="btn btn-warning" title="Edit"><i class="mdi mdi-pencil"></i></a>';
                if ($a->status === FixedAsset::STATUS_ACTIVE) {
                    $actions .= '<button type="button" class="btn btn-danger btn-dispose" data-id="' . $a->id . '" data-name="' . e($a->name) . '" title="Dispose"><i class="mdi mdi-delete"></i></button>';
                }
                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Show form for creating new fixed asset.
     */
    public function create(Request $request)
    {
        $categories = FixedAssetCategory::active()->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        $custodians = User::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $depreciationMethods = [
            FixedAsset::METHOD_STRAIGHT_LINE => 'Straight Line',
            FixedAsset::METHOD_DECLINING_BALANCE => 'Declining Balance',
            FixedAsset::METHOD_DOUBLE_DECLINING => 'Double Declining Balance',
            FixedAsset::METHOD_SUM_OF_YEARS => 'Sum of Years Digits',
            FixedAsset::METHOD_UNITS_OF_PRODUCTION => 'Units of Production',
        ];

        // Pre-select category if provided
        $selectedCategory = null;
        if ($request->filled('category_id')) {
            $selectedCategory = FixedAssetCategory::find($request->category_id);
        }

        return view('accounting.fixed-assets.create', compact(
            'categories',
            'departments',
            'suppliers',
            'custodians',
            'depreciationMethods',
            'selectedCategory'
        ));
    }

    /**
     * Store new fixed asset.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:fixed_asset_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'acquisition_cost' => 'required|numeric|min:0.01',
            'additional_costs' => 'nullable|numeric|min:0',
            'salvage_value' => 'nullable|numeric|min:0',
            'depreciation_method' => 'required|in:' . implode(',', [
                FixedAsset::METHOD_STRAIGHT_LINE,
                FixedAsset::METHOD_DECLINING_BALANCE,
                FixedAsset::METHOD_DOUBLE_DECLINING,
                FixedAsset::METHOD_SUM_OF_YEARS,
                FixedAsset::METHOD_UNITS_OF_PRODUCTION,
            ]),
            'useful_life_years' => 'required|integer|min:1|max:100',
            'acquisition_date' => 'required|date',
            'in_service_date' => 'nullable|date|after_or_equal:acquisition_date',
            'serial_number' => 'nullable|string|max:100',
            'model_number' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'custodian_user_id' => 'nullable|exists:users,id',
            'warranty_expiry_date' => 'nullable|date',
            'warranty_provider' => 'nullable|string|max:100',
            'insurance_policy_number' => 'nullable|string|max:100',
            'insurance_expiry_date' => 'nullable|date',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'invoice_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $asset = $this->assetService->createAsset($validated);

            return redirect()
                ->route('accounting.fixed-assets.show', $asset)
                ->with('success', "Fixed asset {$asset->asset_number} created successfully.");

        } catch (\Exception $e) {
            Log::error('Failed to create fixed asset', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create asset: ' . $e->getMessage());
        }
    }

    /**
     * Display asset details.
     */
    public function show(FixedAsset $fixedAsset)
    {
        $fixedAsset->load([
            'category.assetAccount',
            'category.depreciationAccount',
            'category.expenseAccount',
            'department',
            'custodian',
            'supplier',
            'journalEntry.lines.account',
            'depreciations' => fn($q) => $q->latest()->limit(12),
            'disposals.journalEntry',
        ]);

        // Calculate depreciation schedule
        $depreciationSchedule = $this->assetService->calculateDepreciationSchedule($fixedAsset);

        // Get depreciation history
        $depreciationHistory = FixedAssetDepreciation::where('fixed_asset_id', $fixedAsset->id)
            ->with('journalEntry')
            ->orderBy('depreciation_date', 'desc')
            ->limit(24)
            ->get();

        return view('accounting.fixed-assets.show', compact('fixedAsset', 'depreciationSchedule', 'depreciationHistory'));
    }

    /**
     * Show form for editing asset.
     */
    public function edit(FixedAsset $fixedAsset)
    {
        $categories = FixedAssetCategory::active()->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        $custodians = User::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $depreciationMethods = [
            FixedAsset::METHOD_STRAIGHT_LINE => 'Straight Line',
            FixedAsset::METHOD_DECLINING_BALANCE => 'Declining Balance',
            FixedAsset::METHOD_DOUBLE_DECLINING => 'Double Declining Balance',
            FixedAsset::METHOD_SUM_OF_YEARS => 'Sum of Years Digits',
            FixedAsset::METHOD_UNITS_OF_PRODUCTION => 'Units of Production',
        ];

        $statusOptions = [
            FixedAsset::STATUS_ACTIVE => 'Active',
            FixedAsset::STATUS_UNDER_MAINTENANCE => 'Under Maintenance',
            FixedAsset::STATUS_IDLE => 'Idle',
        ];

        return view('accounting.fixed-assets.edit', compact(
            'fixedAsset',
            'categories',
            'departments',
            'suppliers',
            'custodians',
            'depreciationMethods',
            'statusOptions'
        ));
    }

    /**
     * Update fixed asset.
     */
    public function update(Request $request, FixedAsset $fixedAsset)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'serial_number' => 'nullable|string|max:100',
            'model_number' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'custodian_user_id' => 'nullable|exists:users,id',
            'warranty_expiry_date' => 'nullable|date',
            'warranty_provider' => 'nullable|string|max:100',
            'insurance_policy_number' => 'nullable|string|max:100',
            'insurance_expiry_date' => 'nullable|date',
            'status' => 'required|in:active,under_maintenance,idle',
            'notes' => 'nullable|string|max:1000',
        ]);

        $fixedAsset->update($validated);

        return redirect()
            ->route('accounting.fixed-assets.show', $fixedAsset)
            ->with('success', 'Fixed asset updated successfully.');
    }

    /**
     * Run monthly depreciation for all eligible assets.
     */
    public function runDepreciation(Request $request)
    {
        $validated = $request->validate([
            'depreciation_date' => 'required|date',
            'category_id' => 'nullable|exists:fixed_asset_categories,id',
        ]);

        $depreciationDate = Carbon::parse($validated['depreciation_date']);

        try {
            $result = $this->assetService->runMonthlyDepreciation(
                $depreciationDate,
                $validated['category_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => "Depreciation completed. Processed {$result['count']} assets. Total: ₦" . number_format($result['total'], 2),
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Depreciation run failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Depreciation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dispose of a fixed asset (AJAX).
     */
    public function dispose(Request $request, FixedAsset $fixedAsset)
    {
        $validated = $request->validate([
            'disposal_date' => 'required|date',
            'disposal_type' => 'required|in:sale,scrapped,donation,transfer,write_off',
            'disposal_amount' => 'nullable|numeric|min:0',
            'disposal_reason' => 'required|string|max:500',
            'buyer_info' => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:cash,bank_transfer',
            'bank_id' => 'nullable|exists:banks,id|required_if:payment_method,bank_transfer',
        ]);

        try {
            // Map form fields to service expected names
            $disposalData = [
                'disposal_date' => $validated['disposal_date'],
                'disposal_type' => $validated['disposal_type'],
                'disposal_proceeds' => $validated['disposal_amount'] ?? 0,
                'reason' => $validated['disposal_reason'],
                'buyer_name' => $validated['buyer_info'] ?? null,
                'payment_method' => $validated['payment_method'] ?? null,
                'bank_id' => $validated['bank_id'] ?? null,
            ];

            $disposal = $this->assetService->disposeAsset($fixedAsset, $disposalData);

            return response()->json([
                'success' => true,
                'message' => "Asset disposed successfully. " . ($disposal->gain_loss_amount >= 0
                    ? "Gain: ₦" . number_format($disposal->gain_loss_amount, 2)
                    : "Loss: ₦" . number_format(abs($disposal->gain_loss_amount), 2)),
                'data' => $disposal,
            ]);

        } catch (\Exception $e) {
            Log::error('Asset disposal failed', [
                'asset_id' => $fixedAsset->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Disposal failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get asset details for AJAX requests.
     */
    public function getAsset(FixedAsset $fixedAsset)
    {
        $fixedAsset->load(['category', 'department', 'custodian']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $fixedAsset->id,
                'asset_number' => $fixedAsset->asset_number,
                'name' => $fixedAsset->name,
                'book_value' => $fixedAsset->book_value,
                'accumulated_depreciation' => $fixedAsset->accumulated_depreciation,
                'total_cost' => $fixedAsset->total_cost,
                'status' => $fixedAsset->status,
                'category' => $fixedAsset->category?->name,
                'department' => $fixedAsset->department?->name,
            ],
        ]);
    }

    /**
     * Get depreciation history for an asset.
     */
    public function getDepreciationHistory(FixedAsset $fixedAsset)
    {
        $history = FixedAssetDepreciation::where('fixed_asset_id', $fixedAsset->id)
            ->with('journalEntry')
            ->orderBy('depreciation_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Search assets for AJAX requests.
     */
    public function searchAssets(Request $request)
    {
        $term = $request->get('q', '');

        $assets = FixedAsset::where('status', '!=', FixedAsset::STATUS_DISPOSED)
            ->where(function ($q) use ($term) {
                $q->where('asset_number', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('serial_number', 'like', "%{$term}%");
            })
            ->with(['category', 'department'])
            ->limit(20)
            ->get(['id', 'asset_number', 'name', 'book_value', 'status', 'category_id', 'department_id']);

        return response()->json([
            'results' => $assets->map(fn($a) => [
                'id' => $a->id,
                'text' => "{$a->asset_number} - {$a->name}",
                'book_value' => $a->book_value,
                'category' => $a->category?->name,
                'department' => $a->department?->name,
            ]),
        ]);
    }

    /**
     * Categories management - list categories.
     */
    public function categories()
    {
        $categories = FixedAssetCategory::withCount('fixedAssets')
            ->with(['assetAccount', 'depreciationAccount', 'expenseAccount'])
            ->orderBy('name')
            ->get();

        return view('accounting.fixed-assets.categories', compact('categories'));
    }

    /**
     * Store new category.
     */
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:fixed_asset_categories,code',
            'name' => 'required|string|max:100',
            'asset_account_id' => 'required|exists:accounts,id',
            'depreciation_account_id' => 'required|exists:accounts,id',
            'expense_account_id' => 'required|exists:accounts,id',
            'default_useful_life_years' => 'required|integer|min:1|max:100',
            'default_depreciation_method' => 'required|in:' . implode(',', [
                FixedAssetCategory::METHOD_STRAIGHT_LINE,
                FixedAssetCategory::METHOD_DECLINING_BALANCE,
                FixedAssetCategory::METHOD_DOUBLE_DECLINING,
            ]),
            'default_salvage_percentage' => 'nullable|numeric|min:0|max:100',
            'is_depreciable' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        $validated['is_active'] = true;

        $category = FixedAssetCategory::create($validated);

        return redirect()
            ->back()
            ->with('success', "Category '{$category->name}' created successfully.");
    }

    /**
     * Get category defaults for AJAX.
     */
    public function getCategoryDefaults(FixedAssetCategory $category)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'default_useful_life_years' => $category->default_useful_life_years,
                'default_depreciation_method' => $category->default_depreciation_method,
                'default_salvage_percentage' => $category->default_salvage_percentage,
                'is_depreciable' => $category->is_depreciable,
            ],
        ]);
    }

    /**
     * Export assets report.
     */
    public function export(Request $request)
    {
        $query = FixedAsset::with(['category', 'department', 'custodian']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $assets = $query->orderBy('asset_number')->get();

        // Check export format
        if ($request->format === 'pdf') {
            $stats = [
                'total' => $assets->count(),
                'active' => $assets->where('status', 'active')->count(),
                'total_cost' => $assets->sum('total_cost'),
                'total_depreciation' => $assets->sum('accumulated_depreciation'),
                'total_nbv' => $assets->sum('book_value'),
            ];

            $pdf = Pdf::loadView('accounting.fixed-assets.export-pdf', compact('assets', 'stats'));
            return $pdf->download('fixed-assets-' . now()->format('Y-m-d') . '.pdf');
        }

        // Default to Excel
        $excelService = app(ExcelExportService::class);
        return $excelService->fixedAssets($assets);
    }
}
