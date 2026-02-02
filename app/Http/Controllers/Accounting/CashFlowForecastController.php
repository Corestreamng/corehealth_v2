<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CashFlowForecast;
use App\Models\CashFlowForecastPeriod;
use App\Models\CashFlowForecastItem;
use App\Models\CashFlowPattern;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * CashFlowForecastController
 *
 * Manages cash flow projections and liquidity planning
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
 * Access: SUPERADMIN|ADMIN|ACCOUNTS
 */
class CashFlowForecastController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS']);
    }

    /**
     * Cash flow forecast dashboard
     */
    public function index()
    {
        $stats = $this->getDashboardStats();
        $fiscalYears = FiscalYear::orderBy('year', 'desc')->get();

        // Get current cash position
        $cashAccounts = ChartOfAccount::where('account_type', 'asset')
            ->where(function($q) {
                $q->where('name', 'like', '%cash%')
                  ->orWhere('name', 'like', '%bank%');
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
                ->whereBetween('period_start', [$currentMonth, $nextThreeMonths])
                ->get();

            $periodCount = $periods->count();
            $forecastedInflows = $periods->sum('forecasted_inflows');
            $forecastedOutflows = $periods->sum('forecasted_outflows');
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
        $cashAccounts = ChartOfAccount::where('account_type', 'asset')
            ->where(function($q) {
                $q->where('code', 'like', '1001%')
                  ->orWhere('code', 'like', '1002%')
                  ->orWhere('name', 'like', '%cash%')
                  ->orWhere('name', 'like', '%bank%');
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
        $lastPeriod = CashFlowForecastPeriod::where('period_end', '<', now())
            ->where('actual_inflows', '>', 0)
            ->orWhere('actual_outflows', '>', 0)
            ->orderBy('period_end', 'desc')
            ->first();

        if (!$lastPeriod) {
            return null;
        }

        $forecastedNet = $lastPeriod->forecasted_inflows - $lastPeriod->forecasted_outflows;
        $actualNet = $lastPeriod->actual_inflows - $lastPeriod->actual_outflows;

        return $actualNet - $forecastedNet;
    }

    /**
     * DataTable for forecasts
     */
    public function datatable(Request $request)
    {
        $query = CashFlowForecast::with(['createdBy']);

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
                'created_by' => $forecast->createdBy->name ?? 'System',
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
        $patterns = CashFlowPattern::where('is_active', true)->orderBy('name')->get();

        return view('accounting.cash-flow-forecast.create', compact('patterns'));
    }

    /**
     * Store forecast
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'frequency' => 'required|in:daily,weekly,monthly',
            'description' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $forecast = CashFlowForecast::create([
                'name' => $request->name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'frequency' => $request->frequency,
                'description' => $request->description,
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
     * Generate forecast periods based on frequency
     */
    protected function generatePeriods(CashFlowForecast $forecast)
    {
        $startDate = Carbon::parse($forecast->start_date);
        $endDate = Carbon::parse($forecast->end_date);

        $current = $startDate->copy();

        while ($current->lt($endDate)) {
            $periodEnd = null;

            switch ($forecast->frequency) {
                case 'daily':
                    $periodEnd = $current->copy()->endOfDay();
                    break;
                case 'weekly':
                    $periodEnd = $current->copy()->addDays(6)->endOfDay();
                    break;
                case 'monthly':
                    $periodEnd = $current->copy()->endOfMonth();
                    break;
            }

            // Don't exceed forecast end date
            if ($periodEnd->gt($endDate)) {
                $periodEnd = $endDate;
            }

            CashFlowForecastPeriod::create([
                'cash_flow_forecast_id' => $forecast->id,
                'period_start' => $current->startOfDay(),
                'period_end' => $periodEnd,
                'forecasted_inflows' => 0,
                'forecasted_outflows' => 0,
                'actual_inflows' => 0,
                'actual_outflows' => 0
            ]);

            // Move to next period
            switch ($forecast->frequency) {
                case 'daily':
                    $current->addDay();
                    break;
                case 'weekly':
                    $current->addWeek();
                    break;
                case 'monthly':
                    $current->addMonth()->startOfMonth();
                    break;
            }
        }
    }

    /**
     * Show forecast details
     */
    public function show(CashFlowForecast $forecast)
    {
        $forecast->load(['periods.items', 'createdBy']);

        // Calculate running balance
        $currentCash = $this->getCurrentCashBalance();
        $runningBalance = $currentCash;

        $periodsWithBalance = $forecast->periods->map(function($period) use (&$runningBalance) {
            $period->beginning_balance = $runningBalance;
            $netCashFlow = $period->forecasted_inflows - $period->forecasted_outflows;
            $runningBalance += $netCashFlow;
            $period->ending_balance = $runningBalance;
            $period->net_cash_flow = $netCashFlow;

            // Calculate variance if actuals exist
            if ($period->actual_inflows > 0 || $period->actual_outflows > 0) {
                $actualNet = $period->actual_inflows - $period->actual_outflows;
                $period->variance = $actualNet - $netCashFlow;
            } else {
                $period->variance = null;
            }

            return $period;
        });

        // Chart data
        $chartData = $periodsWithBalance->map(function($period) {
            return [
                'period' => Carbon::parse($period->period_start)->format('M d'),
                'forecasted_inflows' => $period->forecasted_inflows,
                'forecasted_outflows' => $period->forecasted_outflows,
                'actual_inflows' => $period->actual_inflows,
                'actual_outflows' => $period->actual_outflows,
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

        // Get expense categories for items
        $expenseAccounts = ChartOfAccount::where('account_type', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $revenueAccounts = ChartOfAccount::where('account_type', 'revenue')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('accounting.cash-flow-forecast.edit-period', compact('period', 'expenseAccounts', 'revenueAccounts'));
    }

    /**
     * Update period forecasts
     */
    public function updatePeriod(Request $request, CashFlowForecastPeriod $period)
    {
        $request->validate([
            'forecasted_inflows' => 'required|numeric|min:0',
            'forecasted_outflows' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        $period->update([
            'forecasted_inflows' => $request->forecasted_inflows,
            'forecasted_outflows' => $request->forecasted_outflows,
            'notes' => $request->notes
        ]);

        return redirect()->route('accounting.cash-flow-forecast.show', $period->cash_flow_forecast_id)
                       ->with('success', 'Period forecast updated successfully');
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
     * Update actual amounts
     */
    public function updateActuals(Request $request, CashFlowForecastPeriod $period)
    {
        $request->validate([
            'actual_inflows' => 'required|numeric|min:0',
            'actual_outflows' => 'required|numeric|min:0'
        ]);

        $period->update([
            'actual_inflows' => $request->actual_inflows,
            'actual_outflows' => $request->actual_outflows
        ]);

        return response()->json(['success' => true, 'message' => 'Actuals updated successfully']);
    }

    /**
     * Patterns management
     */
    public function patterns()
    {
        $patterns = CashFlowPattern::orderBy('name')->get();

        return view('accounting.cash-flow-forecast.patterns', compact('patterns'));
    }

    /**
     * Store pattern
     */
    public function storePattern(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:inflow,outflow',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly,yearly',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string'
        ]);

        CashFlowPattern::create([
            'name' => $request->name,
            'type' => $request->type,
            'frequency' => $request->frequency,
            'amount' => $request->amount,
            'description' => $request->description,
            'is_active' => true
        ]);

        return redirect()->route('accounting.cash-flow-forecast.patterns.index')
                       ->with('success', 'Pattern created successfully');
    }

    /**
     * Activate forecast
     */
    public function activate(CashFlowForecast $forecast)
    {
        // Deactivate other forecasts
        CashFlowForecast::where('status', 'active')->update(['status' => 'closed']);

        $forecast->update(['status' => 'active']);

        return response()->json(['success' => true, 'message' => 'Forecast activated']);
    }

    /**
     * Export PDF
     */
    public function exportPdf(CashFlowForecast $forecast)
    {
        // Implementation for PDF export
        return back()->with('info', 'PDF export coming soon');
    }

    /**
     * Export Excel
     */
    public function exportExcel(CashFlowForecast $forecast)
    {
        // Implementation for Excel export
        return back()->with('info', 'Excel export coming soon');
    }
}
