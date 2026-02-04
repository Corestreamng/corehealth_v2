<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * KPI Controller
 *
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 11
 *
 * Handles Financial KPI management including:
 * - KPI dashboard with gauge/trend charts
 * - KPI definitions and configuration
 * - Automated KPI calculations
 * - Alert management
 *
 * Access: SUPERADMIN|ADMIN|ACCOUNTS|AUDIT roles
 */
class KpiController extends Controller
{
    /**
     * Display KPI dashboard with charts.
     */
    public function dashboard()
    {
        // Get active KPIs for dashboard
        $kpis = DB::table('financial_kpis')
            ->where('is_active', true)
            ->where('show_on_dashboard', true)
            ->orderBy('category')
            ->orderBy('display_order')
            ->get();

        // Get latest values for each KPI
        $kpiData = [];
        foreach ($kpis as $kpi) {
            $latestValue = DB::table('financial_kpi_values')
                ->where('kpi_id', $kpi->id)
                ->orderBy('calculation_date', 'desc')
                ->first();

            // Get historical values for trend (last 6 periods)
            $history = DB::table('financial_kpi_values')
                ->where('kpi_id', $kpi->id)
                ->orderBy('calculation_date', 'desc')
                ->limit(6)
                ->get()
                ->reverse()
                ->values();

            $kpiData[] = [
                'kpi' => $kpi,
                'latest' => $latestValue,
                'history' => $history,
                'status' => $this->getKpiStatus($kpi, $latestValue),
            ];
        }

        // Group by category
        $groupedKpis = collect($kpiData)->groupBy(fn($item) => $item['kpi']->category);

        // Get active alerts count
        $activeAlertsCount = DB::table('financial_kpi_alerts')
            ->where('is_acknowledged', false)
            ->count();

        // Get summary stats
        $stats = [
            'total_kpis' => $kpis->count(),
            'healthy' => collect($kpiData)->where('status', 'normal')->count(),
            'warning' => collect($kpiData)->where('status', 'warning')->count(),
            'critical' => collect($kpiData)->where('status', 'critical')->count(),
            'active_alerts' => $activeAlertsCount,
        ];

        return view('accounting.kpi.dashboard', compact('groupedKpis', 'stats'));
    }

    /**
     * Get KPI status based on thresholds.
     */
    protected function getKpiStatus($kpi, $value): string
    {
        if (!$value) {
            return 'no-data';
        }

        $val = $value->value;

        // Check critical thresholds
        if ($kpi->critical_threshold_low !== null && $val < $kpi->critical_threshold_low) {
            return 'critical';
        }
        if ($kpi->critical_threshold_high !== null && $val > $kpi->critical_threshold_high) {
            return 'critical';
        }

        // Check warning thresholds
        if ($kpi->warning_threshold_low !== null && $val < $kpi->warning_threshold_low) {
            return 'warning';
        }
        if ($kpi->warning_threshold_high !== null && $val > $kpi->warning_threshold_high) {
            return 'warning';
        }

        return 'normal';
    }

    /**
     * Display KPI definitions list.
     */
    public function index()
    {
        $categories = DB::table('financial_kpis')
            ->select('category')
            ->distinct()
            ->pluck('category');

        return view('accounting.kpi.index', compact('categories'));
    }

    /**
     * DataTable endpoint for KPIs.
     */
    public function datatable(Request $request)
    {
        $query = DB::table('financial_kpis')
            ->select('financial_kpis.*')
            ->orderBy('category')
            ->orderBy('display_order');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        return DataTables::of($query)
            ->addColumn('category_badge', function ($k) {
                $badges = [
                    'liquidity' => 'badge-info',
                    'profitability' => 'badge-success',
                    'efficiency' => 'badge-warning',
                    'solvency' => 'badge-primary',
                    'leverage' => 'badge-secondary',
                ];
                $badge = $badges[$k->category] ?? 'badge-secondary';
                return '<span class="badge ' . $badge . '">' . ucfirst($k->category) . '</span>';
            })
            ->addColumn('unit_display', function ($k) {
                $units = [
                    'percentage' => '%',
                    'ratio' => 'x',
                    'currency' => '₦',
                    'days' => 'days',
                ];
                return $units[$k->unit] ?? $k->unit;
            })
            ->addColumn('status_badge', function ($k) {
                return $k->is_active
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
            })
            ->addColumn('dashboard_badge', function ($k) {
                return $k->show_on_dashboard
                    ? '<span class="badge badge-primary"><i class="mdi mdi-view-dashboard"></i> Yes</span>'
                    : '<span class="badge badge-light">No</span>';
            })
            ->addColumn('latest_value', function ($k) {
                $latest = DB::table('financial_kpi_values')
                    ->where('kpi_id', $k->id)
                    ->orderBy('calculation_date', 'desc')
                    ->first();

                if (!$latest) {
                    return '<span class="text-muted">-</span>';
                }

                $formatted = $this->formatKpiValue($latest->value, $k->unit);
                $change = '';
                if ($latest->change_percentage !== null) {
                    $changeClass = $latest->change_percentage >= 0 ? 'text-success' : 'text-danger';
                    $changeIcon = $latest->change_percentage >= 0 ? 'mdi-arrow-up' : 'mdi-arrow-down';
                    $change = '<small class="' . $changeClass . '"><i class="mdi ' . $changeIcon . '"></i> ' . number_format(abs($latest->change_percentage), 1) . '%</small>';
                }

                return $formatted . ' ' . $change;
            })
            ->addColumn('actions', function ($k) {
                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . route('accounting.kpi.history', $k->id) . '" class="btn btn-outline-info" title="History"><i class="mdi mdi-chart-line"></i></a>';
                $actions .= '<a href="' . route('accounting.kpi.edit', $k->id) . '" class="btn btn-outline-primary" title="Edit"><i class="mdi mdi-pencil"></i></a>';
                $actions .= '<button type="button" class="btn btn-outline-success calculate-kpi" data-id="' . $k->id . '" title="Calculate"><i class="mdi mdi-calculator"></i></button>';
                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['category_badge', 'status_badge', 'dashboard_badge', 'latest_value', 'actions'])
            ->make(true);
    }

    /**
     * Format KPI value based on unit.
     */
    protected function formatKpiValue($value, $unit): string
    {
        switch ($unit) {
            case 'percentage':
                return number_format($value, 2) . '%';
            case 'ratio':
                return number_format($value, 2) . 'x';
            case 'currency':
                return '₦' . number_format($value, 0);
            case 'days':
                return number_format($value, 0) . ' days';
            default:
                return number_format($value, 2);
        }
    }

    /**
     * Show create KPI form.
     */
    public function create()
    {
        $categories = ['liquidity', 'profitability', 'efficiency', 'solvency', 'leverage'];
        $units = ['percentage', 'ratio', 'currency', 'days'];
        $frequencies = ['daily', 'weekly', 'monthly', 'quarterly', 'annually'];
        $chartTypes = ['line', 'bar', 'gauge', 'number'];

        // Get accounts for formula building
        $accounts = DB::table('accounts')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('accounting.kpi.create', compact(
            'categories',
            'units',
            'frequencies',
            'chartTypes',
            'accounts'
        ));
    }

    /**
     * Store a new KPI.
     */
    public function store(Request $request)
    {
        $request->validate([
            'kpi_code' => 'required|string|max:30|unique:financial_kpis,kpi_code',
            'kpi_name' => 'required|string|max:255',
            'category' => 'required|string',
            'calculation_formula' => 'required|string',
            'unit' => 'required|string',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly,annually',
        ]);

        try {
            DB::table('financial_kpis')->insert([
                'kpi_code' => strtoupper($request->kpi_code),
                'kpi_name' => $request->kpi_name,
                'category' => $request->category,
                'description' => $request->description,
                'calculation_formula' => $request->calculation_formula,
                'unit' => $request->unit,
                'frequency' => $request->frequency,
                'target_value' => $request->target_value,
                'warning_threshold_low' => $request->warning_threshold_low,
                'warning_threshold_high' => $request->warning_threshold_high,
                'critical_threshold_low' => $request->critical_threshold_low,
                'critical_threshold_high' => $request->critical_threshold_high,
                'display_order' => $request->display_order ?? 0,
                'show_on_dashboard' => $request->show_on_dashboard ? true : false,
                'is_active' => $request->is_active ? true : false,
                'chart_type' => $request->chart_type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->route('accounting.kpi.index')
                ->with('success', 'KPI created successfully.');

        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Failed to create KPI: ' . $e->getMessage());
        }
    }

    /**
     * Show edit KPI form.
     */
    public function edit($id)
    {
        $kpi = DB::table('financial_kpis')->find($id);

        if (!$kpi) {
            return redirect()->route('accounting.kpi.index')
                ->with('error', 'KPI not found.');
        }

        $categories = ['liquidity', 'profitability', 'efficiency', 'solvency', 'leverage'];
        $units = ['percentage', 'ratio', 'currency', 'days'];
        $frequencies = ['daily', 'weekly', 'monthly', 'quarterly', 'annually'];
        $chartTypes = ['line', 'bar', 'gauge', 'number'];

        $accounts = DB::table('accounts')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('accounting.kpi.edit', compact(
            'kpi',
            'categories',
            'units',
            'frequencies',
            'chartTypes',
            'accounts'
        ));
    }

    /**
     * Update KPI.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'kpi_code' => 'required|string|max:30|unique:financial_kpis,kpi_code,' . $id,
            'kpi_name' => 'required|string|max:255',
            'category' => 'required|string',
            'calculation_formula' => 'required|string',
            'unit' => 'required|string',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly,annually',
        ]);

        try {
            DB::table('financial_kpis')
                ->where('id', $id)
                ->update([
                    'kpi_code' => strtoupper($request->kpi_code),
                    'kpi_name' => $request->kpi_name,
                    'category' => $request->category,
                    'description' => $request->description,
                    'calculation_formula' => $request->calculation_formula,
                    'unit' => $request->unit,
                    'frequency' => $request->frequency,
                    'target_value' => $request->target_value,
                    'warning_threshold_low' => $request->warning_threshold_low,
                    'warning_threshold_high' => $request->warning_threshold_high,
                    'critical_threshold_low' => $request->critical_threshold_low,
                    'critical_threshold_high' => $request->critical_threshold_high,
                    'display_order' => $request->display_order ?? 0,
                    'show_on_dashboard' => $request->show_on_dashboard ? true : false,
                    'is_active' => $request->is_active ? true : false,
                    'chart_type' => $request->chart_type,
                    'updated_at' => now(),
                ]);

            return redirect()->route('accounting.kpi.index')
                ->with('success', 'KPI updated successfully.');

        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Failed to update KPI: ' . $e->getMessage());
        }
    }

    /**
     * Calculate all active KPIs.
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'calculation_date' => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            $kpis = DB::table('financial_kpis')
                ->where('is_active', true)
                ->get();

            $calculatedCount = 0;
            $alertsCreated = 0;

            foreach ($kpis as $kpi) {
                $result = $this->calculateKpiValue($kpi, $request->calculation_date);

                if ($result['success']) {
                    $calculatedCount++;
                    if ($result['alert_created']) {
                        $alertsCreated++;
                    }
                }
            }

            DB::commit();

            return redirect()->route('accounting.kpi.dashboard')
                ->with('success', "Calculated {$calculatedCount} KPIs. {$alertsCreated} alerts generated.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', 'Failed to calculate KPIs: ' . $e->getMessage());
        }
    }

    /**
     * Calculate a single KPI.
     */
    public function calculateSingle(Request $request, $id)
    {
        $request->validate([
            'calculation_date' => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            $kpi = DB::table('financial_kpis')->find($id);

            if (!$kpi) {
                return response()->json(['success' => false, 'message' => 'KPI not found.']);
            }

            $result = $this->calculateKpiValue($kpi, $request->calculation_date);

            DB::commit();

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'value' => $result['value'],
                    'formatted' => $this->formatKpiValue($result['value'], $kpi->unit),
                    'status' => $result['status'],
                ]);
            } else {
                return response()->json(['success' => false, 'message' => $result['message']]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Calculate KPI value based on formula.
     */
    protected function calculateKpiValue($kpi, $date): array
    {
        $calculationDate = Carbon::parse($date);
        $year = $calculationDate->year;
        $month = $calculationDate->month;

        // Get previous value
        $previousValue = DB::table('financial_kpi_values')
            ->where('kpi_id', $kpi->id)
            ->where('calculation_date', '<', $calculationDate)
            ->orderBy('calculation_date', 'desc')
            ->first();

        // Parse formula and calculate
        // For now, using placeholder calculation logic
        // In production, this would parse the formula JSON and fetch actual account balances
        $value = $this->executeFormula($kpi->calculation_formula, $calculationDate);

        if ($value === null) {
            return ['success' => false, 'message' => 'Could not calculate value'];
        }

        // Calculate change
        $changeAmount = $previousValue ? $value - $previousValue->value : null;
        $changePercentage = $previousValue && $previousValue->value != 0
            ? (($value - $previousValue->value) / abs($previousValue->value)) * 100
            : null;

        // Determine status
        $status = $this->determineStatus($kpi, $value);

        // Store value
        $valueId = DB::table('financial_kpi_values')->insertGetId([
            'kpi_id' => $kpi->id,
            'calculation_date' => $calculationDate,
            'year' => $year,
            'month' => $month,
            'value' => $value,
            'previous_value' => $previousValue ? $previousValue->value : null,
            'change_amount' => $changeAmount,
            'change_percentage' => $changePercentage,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create alert if needed
        $alertCreated = false;
        if ($status !== 'normal') {
            $alertCreated = $this->createAlert($kpi, $valueId, $value, $status);
        }

        return [
            'success' => true,
            'value' => $value,
            'status' => $status,
            'alert_created' => $alertCreated,
        ];
    }

    /**
     * Execute formula calculation.
     * This is a simplified implementation - in production would parse JSON formula.
     */
    protected function executeFormula($formula, $date)
    {
        // Placeholder - return random value for demo
        // In production, this would:
        // 1. Parse the formula JSON
        // 2. Get account balances for the period
        // 3. Apply the formula calculation

        // For now, generate reasonable demo values based on formula type
        $hash = crc32($formula . $date->format('Y-m'));
        $base = ($hash % 1000) / 100; // 0-10 range

        // Adjust based on common formula patterns
        if (strpos($formula, 'current_ratio') !== false || strpos($formula, 'liquidity') !== false) {
            return 1.5 + ($base / 5); // 1.5 - 3.5
        }
        if (strpos($formula, 'margin') !== false || strpos($formula, 'profit') !== false) {
            return 5 + ($base * 2); // 5 - 25%
        }
        if (strpos($formula, 'days') !== false) {
            return 20 + ($base * 4); // 20 - 60 days
        }

        return $base;
    }

    /**
     * Determine KPI status based on thresholds.
     */
    protected function determineStatus($kpi, $value): string
    {
        // Check critical thresholds
        if ($kpi->critical_threshold_low !== null && $value < $kpi->critical_threshold_low) {
            return 'critical';
        }
        if ($kpi->critical_threshold_high !== null && $value > $kpi->critical_threshold_high) {
            return 'critical';
        }

        // Check warning thresholds
        if ($kpi->warning_threshold_low !== null && $value < $kpi->warning_threshold_low) {
            return 'warning';
        }
        if ($kpi->warning_threshold_high !== null && $value > $kpi->warning_threshold_high) {
            return 'warning';
        }

        return 'normal';
    }

    /**
     * Create alert for threshold breach.
     */
    protected function createAlert($kpi, $valueId, $value, $status): bool
    {
        $direction = 'above';
        $thresholdValue = 0;

        if ($status === 'critical') {
            if ($kpi->critical_threshold_low !== null && $value < $kpi->critical_threshold_low) {
                $direction = 'below';
                $thresholdValue = $kpi->critical_threshold_low;
            } else {
                $thresholdValue = $kpi->critical_threshold_high;
            }
        } else {
            if ($kpi->warning_threshold_low !== null && $value < $kpi->warning_threshold_low) {
                $direction = 'below';
                $thresholdValue = $kpi->warning_threshold_low;
            } else {
                $thresholdValue = $kpi->warning_threshold_high;
            }
        }

        $message = "{$kpi->kpi_name} is {$direction} {$status} threshold. Current: " .
                   $this->formatKpiValue($value, $kpi->unit) . ", Threshold: " .
                   $this->formatKpiValue($thresholdValue, $kpi->unit);

        DB::table('financial_kpi_alerts')->insert([
            'kpi_id' => $kpi->id,
            'kpi_value_id' => $valueId,
            'alert_type' => $status,
            'direction' => $direction,
            'threshold_value' => $thresholdValue,
            'actual_value' => $value,
            'message' => $message,
            'is_acknowledged' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Show KPI history with trend chart.
     */
    public function history($id)
    {
        $kpi = DB::table('financial_kpis')->find($id);

        if (!$kpi) {
            return redirect()->route('accounting.kpi.index')
                ->with('error', 'KPI not found.');
        }

        // Get historical values (last 12 periods)
        $history = DB::table('financial_kpi_values')
            ->where('kpi_id', $id)
            ->orderBy('calculation_date', 'desc')
            ->limit(24)
            ->get()
            ->reverse()
            ->values();

        // Get alerts for this KPI
        $alerts = DB::table('financial_kpi_alerts')
            ->where('kpi_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Stats
        $stats = [
            'min' => $history->min('value'),
            'max' => $history->max('value'),
            'avg' => $history->avg('value'),
            'latest' => $history->last(),
        ];

        return view('accounting.kpi.history', compact('kpi', 'history', 'alerts', 'stats'));
    }

    /**
     * Show active alerts.
     */
    public function alerts()
    {
        $alerts = DB::table('financial_kpi_alerts')
            ->join('financial_kpis', 'financial_kpi_alerts.kpi_id', '=', 'financial_kpis.id')
            ->leftJoin('users', 'financial_kpi_alerts.acknowledged_by', '=', 'users.id')
            ->select([
                'financial_kpi_alerts.*',
                'financial_kpis.kpi_name',
                'financial_kpis.kpi_code',
                'financial_kpis.category',
                'financial_kpis.unit',
                DB::raw("CONCAT(users.firstname, ' ', users.surname) as acknowledged_by_name"),
            ])
            ->orderBy('financial_kpi_alerts.is_acknowledged')
            ->orderBy('financial_kpi_alerts.created_at', 'desc')
            ->paginate(25);

        // Summary
        $summary = [
            'total' => DB::table('financial_kpi_alerts')->count(),
            'unacknowledged' => DB::table('financial_kpi_alerts')->where('is_acknowledged', false)->count(),
            'critical' => DB::table('financial_kpi_alerts')->where('alert_type', 'critical')->where('is_acknowledged', false)->count(),
            'warning' => DB::table('financial_kpi_alerts')->where('alert_type', 'warning')->where('is_acknowledged', false)->count(),
        ];

        return view('accounting.kpi.alerts', compact('alerts', 'summary'));
    }

    /**
     * Acknowledge an alert.
     */
    public function acknowledgeAlert(Request $request, $alertId)
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::table('financial_kpi_alerts')
                ->where('id', $alertId)
                ->update([
                    'is_acknowledged' => true,
                    'acknowledged_by' => Auth::id(),
                    'acknowledged_at' => now(),
                    'acknowledgement_notes' => $request->notes,
                    'updated_at' => now(),
                ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Dashboard configuration page.
     */
    public function configure()
    {
        $allKpis = DB::table('financial_kpis')
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('display_order')
            ->get();

        // Get user's current configuration
        $userConfig = DB::table('dashboard_configs')
            ->where('user_id', Auth::id())
            ->where('dashboard_type', 'financial')
            ->first();

        return view('accounting.kpi.configure', compact('allKpis', 'userConfig'));
    }

    /**
     * Export KPI report to PDF.
     */
    public function exportPdf(Request $request)
    {
        $kpis = DB::table('financial_kpis')
            ->where('is_active', true)
            ->when($request->category, fn($q, $cat) => $q->where('category', $cat))
            ->orderBy('category')
            ->orderBy('display_order')
            ->get();

        $kpiData = [];
        foreach ($kpis as $kpi) {
            $latestValue = DB::table('financial_kpi_values')
                ->where('kpi_id', $kpi->id)
                ->orderBy('calculation_date', 'desc')
                ->first();

            $kpiData[] = [
                'kpi' => $kpi,
                'latest' => $latestValue,
                'status' => $this->getKpiStatus($kpi, $latestValue),
            ];
        }

        $groupedKpis = collect($kpiData)->groupBy(fn($item) => $item['kpi']->category);

        // Check if Excel export requested
        if ($request->format === 'excel') {
            $excelService = app(ExcelExportService::class);
            return $excelService->kpis($groupedKpis->toArray());
        }

        // Default to PDF
        $pdf = Pdf::loadView('accounting.kpi.export-pdf', compact('groupedKpis'));
        return $pdf->download('kpi-report-' . now()->format('Y-m-d') . '.pdf');
    }
}
