<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CashFlowForecast;
use App\Models\Accounting\CashFlowForecastPeriod;
use App\Models\Accounting\CashFlowForecastItem;
use App\Models\Accounting\CashFlowPattern;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * CashFlowForecastController
 *
 * Manages cash flow projections and liquidity planning
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
 * Access: SUPERADMIN|ADMIN|ACCOUNTS
 */
class CashFlowForecastController extends Controller
{
    protected ExcelExportService $excelService;

    public function __construct(ExcelExportService $excelService)
    {
        $this->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS']);
        $this->excelService = $excelService;
    }

    /**
     * Cash flow forecast dashboard
     */
    public function index()
    {
        $stats = $this->getDashboardStats();
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();

        // Get current cash position - accounts in Asset class (code starts with 1)
        $cashAccounts = Account::whereHas('accountGroup.accountClass', function($q) {
                $q->where('code', '1'); // Asset class
            })
            ->where(function($q) {
                $q->where('name', 'like', '%cash%')
                  ->orWhere('name', 'like', '%bank%')
                  ->orWhere('is_bank_account', true);
            })
            ->where('is_active', true)
            ->get();

        return view('accounting.cash-flow-forecast.index', compact('stats', 'fiscalYears', 'cashAccounts'));
    }

    /**
     * Get dashboard statistics
     */
    protected function getDashboardStats()
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $nextThreeMonths = Carbon::now()->addMonths(3)->endOfMonth();

        // Current cash position
        $currentCash = $this->getCurrentCashBalance();

        // Get active forecasts
        $activeForecast = CashFlowForecast::where('status', 'active')
            ->where('end_date', '>=', now())
            ->orderBy('created_at', 'desc')
            ->first();

        $forecastedInflows = 0;
        $forecastedOutflows = 0;
        $periodCount = 0;

        if ($activeForecast) {
            $periods = $activeForecast->periods()
                ->whereBetween('period_start_date', [$currentMonth, $nextThreeMonths])
                ->get();

            $periodCount = $periods->count();
            // Use computed accessors from model
            $forecastedInflows = $periods->sum(fn($p) => $p->forecasted_inflows);
            $forecastedOutflows = $periods->sum(fn($p) => $p->forecasted_outflows);
        }

        // Net forecast
        $netForecast = $forecastedInflows - $forecastedOutflows;
        $projectedEndingCash = $currentCash + $netForecast;

        // Forecast count
        $totalForecasts = CashFlowForecast::count();
        $activeForecasts = CashFlowForecast::where('status', 'active')->count();

        // Variance from last period actual
        $lastPeriodVariance = $this->getLastPeriodVariance();

        return [
            'current_cash' => $currentCash,
            'forecasted_inflows' => $forecastedInflows,
            'forecasted_outflows' => $forecastedOutflows,
            'net_forecast' => $netForecast,
            'projected_ending_cash' => $projectedEndingCash,
            'total_forecasts' => $totalForecasts,
            'active_forecasts' => $activeForecasts,
            'period_count' => $periodCount,
            'last_period_variance' => $lastPeriodVariance,
            'active_forecast' => $activeForecast
        ];
    }

    /**
     * Get current cash balance from GL
     */
    protected function getCurrentCashBalance()
    {
        $cashAccounts = Account::whereHas('accountGroup.accountClass', function($q) {
                $q->where('code', '1'); // Asset class
            })
            ->where(function($q) {
                $q->where('code', 'like', '1001%')
                  ->orWhere('code', 'like', '1002%')
                  ->orWhere('name', 'like', '%cash%')
                  ->orWhere('name', 'like', '%bank%')
                  ->orWhere('is_bank_account', true);
            })
            ->where('is_active', true)
            ->pluck('id');

        $debits = JournalEntryLine::whereHas('journalEntry', function($q) {
            $q->where('status', 'posted');
        })
        ->whereIn('account_id', $cashAccounts)
        ->sum('debit');

        $credits = JournalEntryLine::whereHas('journalEntry', function($q) {
            $q->where('status', 'posted');
        })
        ->whereIn('account_id', $cashAccounts)
        ->sum('credit');

        return $debits - $credits;
    }

    /**
     * Get variance from last completed period
     */
    protected function getLastPeriodVariance()
    {
        $lastPeriod = CashFlowForecastPeriod::where('period_end_date', '<', now())
            ->whereNotNull('actual_closing_balance')
            ->orderBy('period_end_date', 'desc')
            ->first();

        if (!$lastPeriod) {
            return null;
        }

        // Use the variance field directly if available
        return $lastPeriod->variance ?? 0;
    }

    /**
     * DataTable for forecasts
     */
    public function datatable(Request $request)
    {
        $query = CashFlowForecast::with(['creator']);

        // Filters
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search['value']) {
            $search = $request->search['value'];
            $query->where('name', 'like', "%{$search}%");
        }

        $totalRecords = CashFlowForecast::count();
        $filteredRecords = $query->count();

        // Ordering
        $orderColumn = $request->order[0]['column'] ?? 0;
        $orderDir = $request->order[0]['dir'] ?? 'desc';
        $columns = ['id', 'name', 'start_date', 'end_date', 'status', 'created_at'];

        if (isset($columns[$orderColumn])) {
            $query->orderBy($columns[$orderColumn], $orderDir);
        }

        // Pagination
        $forecasts = $query->skip($request->start ?? 0)
                          ->take($request->length ?? 10)
                          ->get();

        $data = $forecasts->map(function($forecast) {
            $statusColors = [
                'draft' => 'secondary',
                'active' => 'success',
                'closed' => 'dark'
            ];

            $periodCount = $forecast->periods()->count();

            return [
                'id' => $forecast->id,
                'name' => $forecast->name,
                'start_date' => Carbon::parse($forecast->start_date)->format('M d, Y'),
                'end_date' => Carbon::parse($forecast->end_date)->format('M d, Y'),
                'periods' => $periodCount . ' periods',
                'status' => '<span class="badge badge-' . ($statusColors[$forecast->status] ?? 'secondary') . '">' . ucfirst($forecast->status) . '</span>',
                'created_by' => $forecast->creator->name ?? 'System',
                'created_at' => $forecast->created_at->format('M d, Y'),
                'actions' => $this->getActionButtons($forecast)
            ];
        });

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data
        ]);
    }

    /**
     * Generate action buttons
     */
    protected function getActionButtons($forecast)
    {
        $buttons = '<div class="btn-group btn-group-sm">';
        $buttons .= '<a href="' . route('accounting.cash-flow-forecast.show', $forecast->id) . '" class="btn btn-info" title="View"><i class="mdi mdi-eye"></i></a>';

        if ($forecast->status == 'draft') {
            $buttons .= '<button class="btn btn-success activate-forecast" data-id="' . $forecast->id . '" title="Activate"><i class="mdi mdi-check"></i></button>';
        }

        $buttons .= '</div>';
        return $buttons;
    }

    /**
     * Create form
     */
    public function create()
    {
        $patterns = CashFlowPattern::where('is_active', true)->orderBy('pattern_name')->get();

        return view('accounting.cash-flow-forecast.create', compact('patterns'));
    }

    /**
     * Store forecast
     */
    public function store(Request $request)
    {
        $request->validate([
            'forecast_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'forecast_type' => 'required|in:weekly,monthly,quarterly,annual',
            'scenario' => 'nullable|in:base,optimistic,pessimistic',
            'notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $forecast = CashFlowForecast::create([
                'forecast_name' => $request->forecast_name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'forecast_type' => $request->forecast_type,
                'scenario' => $request->scenario ?? 'base',
                'notes' => $request->notes,
                'status' => 'draft',
                'created_by' => Auth::id()
            ]);

            // Generate periods
            $this->generatePeriods($forecast);

            DB::commit();
            return redirect()->route('accounting.cash-flow-forecast.show', $forecast->id)
                           ->with('success', 'Cash flow forecast created successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Failed to create forecast: ' . $e->getMessage());
        }
    }

    /**
     * Generate forecast periods based on forecast_type
     */
    protected function generatePeriods(CashFlowForecast $forecast)
    {
        $startDate = Carbon::parse($forecast->start_date);
        $endDate = Carbon::parse($forecast->end_date);

        $current = $startDate->copy();
        $periodNumber = 1;

        while ($current->lt($endDate)) {
            $periodEnd = null;

            switch ($forecast->forecast_type) {
                case 'weekly':
                    $periodEnd = $current->copy()->addDays(6)->endOfDay();
                    break;
                case 'monthly':
                    $periodEnd = $current->copy()->endOfMonth();
                    break;
                case 'quarterly':
                    $periodEnd = $current->copy()->addMonths(3)->subDay()->endOfDay();
                    break;
                case 'annual':
                    $periodEnd = $current->copy()->addYear()->subDay()->endOfDay();
                    break;
            }

            // Don't exceed forecast end date
            if ($periodEnd->gt($endDate)) {
                $periodEnd = $endDate;
            }

            CashFlowForecastPeriod::create([
                'forecast_id' => $forecast->id,
                'period_number' => $periodNumber,
                'period_start_date' => $current->startOfDay(),
                'period_end_date' => $periodEnd,
                'opening_balance' => 0,
                'closing_balance' => 0,
            ]);
            $periodNumber++;

            // Move to next period
            switch ($forecast->forecast_type) {
                case 'weekly':
                    $current->addWeek();
                    break;
                case 'monthly':
                    $current->addMonth()->startOfMonth();
                    break;
                case 'quarterly':
                    $current->addMonths(3);
                    break;
                case 'annual':
                    $current->addYear();
                    break;
            }
        }
    }

    /**
     * Show forecast details
     */
    public function show(CashFlowForecast $forecast)
    {
        $forecast->load(['periods.items', 'creator']);

        // Calculate running balance
        $currentCash = $this->getCurrentCashBalance();
        $runningBalance = $currentCash;

        $periodsWithBalance = $forecast->periods->map(function($period) use (&$runningBalance) {
            $period->beginning_balance = $runningBalance;
            // Use model accessors for forecasted amounts
            $netCashFlow = $period->forecasted_inflows - $period->forecasted_outflows;
            $runningBalance += $netCashFlow;
            $period->ending_balance = $runningBalance;
            $period->calculated_net_cash_flow = $netCashFlow;

            return $period;
        });

        // Chart data
        $chartData = $periodsWithBalance->map(function($period) {
            return [
                'period' => Carbon::parse($period->period_start_date)->format('M d'),
                'forecasted_inflows' => $period->forecasted_inflows,
                'forecasted_outflows' => $period->forecasted_outflows,
                'actual_closing_balance' => $period->actual_closing_balance,
                'ending_balance' => $period->ending_balance
            ];
        });

        return view('accounting.cash-flow-forecast.show', compact('forecast', 'periodsWithBalance', 'chartData', 'currentCash'));
    }

    /**
     * Edit period forecasts
     */
    public function editPeriod(CashFlowForecastPeriod $period)
    {
        $period->load(['forecast', 'items']);

        $openingBalance = $period->opening_balance ?? 0;

        // Get expense categories for items
        $expenseAccounts = Account::whereHas('accountGroup.accountClass', function($q) {
                $q->where('code', '5'); // Expense class
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $revenueAccounts = Account::whereHas('accountGroup.accountClass', function($q) {
                $q->where('code', '4'); // Income/Revenue class
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('accounting.cash-flow-forecast.edit-period', compact('period', 'expenseAccounts', 'revenueAccounts', 'openingBalance'));
    }

    /**
     * Update period forecasts
     */
    public function updatePeriod(Request $request, CashFlowForecastPeriod $period)
    {
        $request->validate([
            'items' => 'nullable|array',
            'items.*.item_description' => 'required|string|max:255',
            'items.*.cash_flow_category' => 'required|in:operating_inflow,operating_outflow,investing_inflow,investing_outflow,financing_inflow,financing_outflow',
            'items.*.forecasted_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            $items = collect($request->input('items', []));

            // Recreate line items for simplicity; periods are small in count
            $period->items()->delete();

            $period->items()->createMany(
                $items->map(function ($item) {
                    return [
                        'item_description' => $item['item_description'] ?? '',
                        'cash_flow_category' => $item['cash_flow_category'],
                        'forecasted_amount' => $item['forecasted_amount'] ?? 0,
                        'source_type' => CashFlowForecastItem::SOURCE_MANUAL,
                    ];
                })->toArray()
            );

            // Calculate totals
            $inflowTotal = $items
                ->filter(fn($item) => str_contains($item['cash_flow_category'], 'inflow'))
                ->sum(fn($item) => (float) ($item['forecasted_amount'] ?? 0));

            $outflowTotal = $items
                ->filter(fn($item) => str_contains($item['cash_flow_category'], 'outflow'))
                ->sum(fn($item) => (float) ($item['forecasted_amount'] ?? 0));

            $netCashFlow = $inflowTotal - $outflowTotal;
            $closingBalance = ($period->opening_balance ?? 0) + $netCashFlow;

            $period->update([
                'net_cash_flow' => $netCashFlow,
                'closing_balance' => $closingBalance,
                'notes' => $request->notes,
            ]);

            DB::commit();

            return redirect()
                ->route('accounting.cash-flow-forecast.show', $period->forecast_id)
                ->with('success', 'Period forecast updated successfully');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => 'Unable to update period: ' . $e->getMessage()]);
        }
    }

    /**
     * Add item to period
     */
    public function addItem(Request $request, CashFlowForecastPeriod $period)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'type' => 'required|in:inflow,outflow',
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:100'
        ]);

        DB::beginTransaction();
        try {
            CashFlowForecastItem::create([
                'cash_flow_forecast_period_id' => $period->id,
                'description' => $request->description,
                'type' => $request->type,
                'amount' => $request->amount,
                'category' => $request->category
            ]);

            // Update period totals
            if ($request->type == 'inflow') {
                $period->increment('forecasted_inflows', $request->amount);
            } else {
                $period->increment('forecasted_outflows', $request->amount);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Item added successfully']);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Failed to add item']);
        }
    }

    /**
     * Apply recurring patterns to all periods in a forecast
     *
     * This method takes all active patterns and creates forecast items for each
     * applicable period based on the pattern frequency and the period dates.
     *
     * Pattern frequency matching:
     * - weekly: Applied to weekly forecasts
     * - bi_weekly: Applied every 2 weeks
     * - monthly: Applied to monthly forecasts OR first week of each month in weekly forecasts
     * - quarterly: Applied to quarterly forecasts OR first period of each quarter
     * - annually: Applied once per year
     */
    public function applyPatterns(Request $request, CashFlowForecast $forecast)
    {
        $request->validate([
            'pattern_ids' => 'nullable|array',
            'pattern_ids.*' => 'exists:cash_flow_recurring_patterns,id',
            'overwrite' => 'nullable'
        ]);

        $patternIds = $request->input('pattern_ids');
        // Handle various boolean representations from JS
        $overwrite = filter_var($request->input('overwrite', false), FILTER_VALIDATE_BOOLEAN);

        // Get patterns - either selected ones or all active patterns
        $patternsQuery = CashFlowPattern::where('is_active', true);
        if (!empty($patternIds)) {
            $patternsQuery->whereIn('id', $patternIds);
        }
        $patterns = $patternsQuery->get();

        if ($patterns->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active patterns found to apply'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $itemsCreated = 0;
            $periodsAffected = 0;

            foreach ($forecast->periods as $period) {
                $periodStart = Carbon::parse($period->period_start_date);
                $periodHadChanges = false;

                // Optionally clear existing pattern-sourced items
                if ($overwrite) {
                    $period->items()->where('source_type', CashFlowForecastItem::SOURCE_PATTERN)->delete();
                }

                foreach ($patterns as $pattern) {
                    // Check if pattern should apply to this period based on frequency matching
                    if (!$this->patternAppliesToPeriod($pattern, $period, $forecast->forecast_type)) {
                        continue;
                    }

                    // Check if this pattern already exists in this period (avoid duplicates)
                    $exists = $period->items()
                        ->where('item_description', $pattern->pattern_name)
                        ->where('cash_flow_category', $pattern->cash_flow_category)
                        ->where('source_type', CashFlowForecastItem::SOURCE_PATTERN)
                        ->exists();

                    if ($exists && !$overwrite) {
                        continue;
                    }

                    // Create the forecast item from pattern
                    $period->items()->create([
                        'item_description' => $pattern->pattern_name,
                        'cash_flow_category' => $pattern->cash_flow_category,
                        'forecasted_amount' => $this->calculatePatternAmount($pattern, $period, $forecast->forecast_type),
                        'source_type' => CashFlowForecastItem::SOURCE_PATTERN,
                        'source_reference' => 'pattern:' . $pattern->id,
                    ]);

                    $itemsCreated++;
                    $periodHadChanges = true;
                }

                if ($periodHadChanges) {
                    $periodsAffected++;
                    // Recalculate period totals
                    $this->recalculatePeriodTotals($period);
                }
            }

            DB::commit();

            Log::info('Patterns applied to forecast', [
                'forecast_id' => $forecast->id,
                'forecast_name' => $forecast->forecast_name,
                'patterns_count' => $patterns->count(),
                'items_created' => $itemsCreated,
                'periods_affected' => $periodsAffected,
                'overwrite' => $overwrite,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully applied {$patterns->count()} patterns. Created {$itemsCreated} items across {$periodsAffected} periods.",
                'items_created' => $itemsCreated,
                'periods_affected' => $periodsAffected
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to apply patterns to forecast', [
                'forecast_id' => $forecast->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to apply patterns: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine if a pattern should apply to a specific period
     */
    protected function patternAppliesToPeriod(CashFlowPattern $pattern, CashFlowForecastPeriod $period, string $forecastType): bool
    {
        $periodStart = Carbon::parse($period->period_start_date);

        switch ($pattern->frequency) {
            case CashFlowPattern::FREQUENCY_WEEKLY:
                // Weekly patterns apply to all weekly periods
                return $forecastType === 'weekly';

            case CashFlowPattern::FREQUENCY_BI_WEEKLY:
                // Bi-weekly applies every other week (odd period numbers)
                return $forecastType === 'weekly' && ($period->period_number % 2 === 1);

            case CashFlowPattern::FREQUENCY_MONTHLY:
                if ($forecastType === 'monthly') {
                    return true;
                }
                // For weekly forecasts, apply to first week of month
                if ($forecastType === 'weekly') {
                    $dayOfPeriod = $pattern->day_of_period ?? 1;
                    return $periodStart->day <= 7 || $periodStart->day === $dayOfPeriod;
                }
                return false;

            case CashFlowPattern::FREQUENCY_QUARTERLY:
                if ($forecastType === 'quarterly') {
                    return true;
                }
                // For monthly, apply to first month of quarter
                if ($forecastType === 'monthly') {
                    return in_array($periodStart->month, [1, 4, 7, 10]);
                }
                return false;

            case CashFlowPattern::FREQUENCY_ANNUALLY:
                // Apply once per year - check if it's the first period or matches day_of_period
                if ($forecastType === 'annual') {
                    return true;
                }
                // For other frequencies, apply in January or first period
                return $period->period_number === 1 || $periodStart->month === 1;

            default:
                return false;
        }
    }

    /**
     * Calculate the amount for a pattern based on period duration
     *
     * Adjusts pattern amount if the forecast period doesn't match pattern frequency
     */
    protected function calculatePatternAmount(CashFlowPattern $pattern, CashFlowForecastPeriod $period, string $forecastType): float
    {
        $baseAmount = (float) $pattern->expected_amount;

        // If frequencies match, use base amount
        if ($pattern->frequency === 'weekly' && $forecastType === 'weekly') {
            return $baseAmount;
        }
        if ($pattern->frequency === 'monthly' && $forecastType === 'monthly') {
            return $baseAmount;
        }
        if ($pattern->frequency === 'quarterly' && $forecastType === 'quarterly') {
            return $baseAmount;
        }
        if ($pattern->frequency === 'annually' && $forecastType === 'annual') {
            return $baseAmount;
        }

        // Convert based on frequency mismatch
        // Pattern is monthly but forecast is weekly - divide by ~4.33
        if ($pattern->frequency === 'monthly' && $forecastType === 'weekly') {
            return round($baseAmount / 4.33, 2);
        }

        // Pattern is weekly but forecast is monthly - multiply by ~4.33
        if ($pattern->frequency === 'weekly' && $forecastType === 'monthly') {
            return round($baseAmount * 4.33, 2);
        }

        // Pattern is quarterly but forecast is monthly - divide by 3
        if ($pattern->frequency === 'quarterly' && $forecastType === 'monthly') {
            return round($baseAmount / 3, 2);
        }

        return $baseAmount;
    }

    /**
     * Recalculate period totals after adding/removing items
     */
    protected function recalculatePeriodTotals(CashFlowForecastPeriod $period): void
    {
        $period->refresh();

        $inflowTotal = $period->items
            ->filter(fn($item) => str_contains($item->cash_flow_category, 'inflow'))
            ->sum('forecasted_amount');

        $outflowTotal = $period->items
            ->filter(fn($item) => str_contains($item->cash_flow_category, 'outflow'))
            ->sum('forecasted_amount');

        $netCashFlow = $inflowTotal - $outflowTotal;

        $period->update([
            'net_cash_flow' => $netCashFlow,
            'closing_balance' => ($period->opening_balance ?? 0) + $netCashFlow
        ]);
    }

    /**
     * Update actual amounts
     */
    public function updateActuals(Request $request, CashFlowForecastPeriod $period)
    {
        $data = $request->validate([
            'actual_closing_balance' => 'nullable|numeric',
            'variance_explanation' => 'nullable|string|max:1000'
        ]);

        // Only compute variance if a closing balance was provided
        if (array_key_exists('actual_closing_balance', $data) && $data['actual_closing_balance'] !== null) {
            $forecastedNet = $period->forecasted_inflows - $period->forecasted_outflows;
            $data['variance'] = $data['actual_closing_balance'] - ($period->opening_balance + $forecastedNet);
        }

        $period->update($data);

        return response()->json(['success' => true, 'message' => 'Actuals updated successfully']);
    }

    /**
     * Patterns management
     */
    public function patterns()
    {
        $patterns = CashFlowPattern::orderBy('pattern_name')->get();

        Log::info('Cash flow patterns viewed', [
            'total_patterns' => $patterns->count(),
            'user_id' => Auth::id()
        ]);

        return view('accounting.cash-flow-forecast.patterns', compact('patterns'));
    }

    /**
     * Store pattern
     */
    public function storePattern(Request $request)
    {
        $request->validate([
            'pattern_name' => 'required|string|max:255',
            'cash_flow_category' => 'required|in:operating_inflow,operating_outflow,investing_inflow,investing_outflow,financing_inflow,financing_outflow',
            'frequency' => 'required|in:weekly,bi_weekly,monthly,quarterly,annually',
            'expected_amount' => 'required|numeric|min:0',
            'day_of_period' => 'nullable|integer|min:1|max:31',
            'variance_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string'
        ]);

        try {
            $pattern = CashFlowPattern::create([
                'pattern_name' => $request->pattern_name,
                'cash_flow_category' => $request->cash_flow_category,
                'frequency' => $request->frequency,
                'expected_amount' => $request->expected_amount,
                'day_of_period' => $request->day_of_period,
                'variance_percentage' => $request->variance_percentage ?? 10,
                'notes' => $request->notes,
                'is_active' => $request->has('is_active')
            ]);

            Log::info('Cash flow pattern created', [
                'pattern_id' => $pattern->id,
                'pattern_name' => $pattern->pattern_name,
                'category' => $pattern->cash_flow_category,
                'amount' => $pattern->expected_amount,
                'user_id' => Auth::id()
            ]);

            return redirect()->route('accounting.cash-flow-forecast.patterns.index')
                           ->with('success', 'Pattern created successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create cash flow pattern', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to create pattern: ' . $e->getMessage());
        }
    }

    /**
     * Update pattern
     */
    public function updatePattern(Request $request, CashFlowPattern $pattern)
    {
        $request->validate([
            'pattern_name' => 'required|string|max:255',
            'cash_flow_category' => 'required|in:operating_inflow,operating_outflow,investing_inflow,investing_outflow,financing_inflow,financing_outflow',
            'frequency' => 'required|in:weekly,bi_weekly,monthly,quarterly,annually',
            'expected_amount' => 'required|numeric|min:0',
            'day_of_period' => 'nullable|integer|min:1|max:31',
            'variance_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string'
        ]);

        try {
            $oldData = $pattern->toArray();

            $pattern->update([
                'pattern_name' => $request->pattern_name,
                'cash_flow_category' => $request->cash_flow_category,
                'frequency' => $request->frequency,
                'expected_amount' => $request->expected_amount,
                'day_of_period' => $request->day_of_period,
                'variance_percentage' => $request->variance_percentage ?? 10,
                'notes' => $request->notes,
                'is_active' => $request->has('is_active')
            ]);

            Log::info('Cash flow pattern updated', [
                'pattern_id' => $pattern->id,
                'pattern_name' => $pattern->pattern_name,
                'old_data' => $oldData,
                'new_data' => $pattern->fresh()->toArray(),
                'user_id' => Auth::id()
            ]);

            return redirect()->route('accounting.cash-flow-forecast.patterns.index')
                           ->with('success', 'Pattern updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update cash flow pattern', [
                'pattern_id' => $pattern->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to update pattern: ' . $e->getMessage());
        }
    }

    /**
     * Delete pattern
     */
    public function deletePattern(CashFlowPattern $pattern)
    {
        try {
            $patternData = $pattern->toArray();

            $pattern->delete();

            Log::info('Cash flow pattern deleted', [
                'pattern_id' => $patternData['id'],
                'pattern_name' => $patternData['pattern_name'],
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pattern deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete cash flow pattern', [
                'pattern_id' => $pattern->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete pattern: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle pattern active status
     */
    public function togglePattern(Request $request, CashFlowPattern $pattern)
    {
        try {
            $oldStatus = $pattern->is_active;
            $newStatus = $request->action === 'activate';

            $pattern->update(['is_active' => $newStatus]);

            Log::info('Cash flow pattern status toggled', [
                'pattern_id' => $pattern->id,
                'pattern_name' => $pattern->pattern_name,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'action' => $request->action,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pattern ' . ($newStatus ? 'activated' : 'deactivated') . ' successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to toggle cash flow pattern status', [
                'pattern_id' => $pattern->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update pattern status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate forecast
     *
     * When activated:
     * - Closes all other active forecasts (only one can be active at a time)
     * - Sets this forecast status to 'active'
     * - This becomes the primary forecast used for cash planning and monitoring
     * - Active forecasts are typically used for current period tracking and variance analysis
     */
    public function activate(CashFlowForecast $forecast)
    {
        DB::beginTransaction();
        try {
            // Close any currently active forecasts
            CashFlowForecast::where('status', 'active')
                ->where('id', '!=', $forecast->id)
                ->update(['status' => 'archived']);

            // Activate this forecast
            $forecast->update([
                'status' => 'active',
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Forecast activated successfully. Previous active forecasts have been archived.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate forecast: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export PDF
     */
    public function exportPdf(CashFlowForecast $forecast)
    {
        // Load the forecast with relationships
        $forecast->load(['periods.items', 'approver']);

        // Get current cash balance
        $currentCash = $this->getCurrentCashBalance();

        // Generate PDF using unified layout
        $pdf = Pdf::loadView('accounting.cash-flow-forecast.pdf.forecast', compact('forecast', 'currentCash'))
            ->setPaper('a4', 'landscape')
            ->setOption('margin-top', 10)
            ->setOption('margin-right', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10);

        return $pdf->download('cash_flow_forecast_' . $forecast->id . '_' . now()->format('Ymd') . '.pdf');
    }

    /**
     * Export Excel
     */
    public function exportExcel(CashFlowForecast $forecast)
    {
        // Load the forecast with relationships
        $forecast->load(['periods.items', 'approver']);

        // Get current cash balance
        $currentCash = $this->getCurrentCashBalance();

        return $this->excelService->cashFlowForecast($forecast, $currentCash);
    }
}
