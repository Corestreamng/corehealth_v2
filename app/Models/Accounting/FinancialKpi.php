<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Financial KPI Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.15
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 10
 */
class FinancialKpi extends Model
{
    use HasFactory;

    protected $table = 'financial_kpis';

    // Categories
    public const CAT_LIQUIDITY = 'liquidity';
    public const CAT_PROFITABILITY = 'profitability';
    public const CAT_EFFICIENCY = 'efficiency';
    public const CAT_SOLVENCY = 'solvency';
    public const CAT_REVENUE = 'revenue';
    public const CAT_CASH_FLOW = 'cash_flow';

    // Frequencies
    public const FREQ_DAILY = 'daily';
    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_MONTHLY = 'monthly';
    public const FREQ_QUARTERLY = 'quarterly';
    public const FREQ_ANNUALLY = 'annually';

    protected $fillable = [
        'kpi_code',
        'kpi_name',
        'category',
        'description',
        'calculation_formula',
        'unit',
        'frequency',
        'target_value',
        'warning_threshold_low',
        'warning_threshold_high',
        'critical_threshold_low',
        'critical_threshold_high',
        'display_order',
        'show_on_dashboard',
        'is_active',
        'chart_type',
    ];

    protected $casts = [
        'calculation_formula' => 'array',
        'target_value' => 'decimal:4',
        'warning_threshold_low' => 'decimal:4',
        'warning_threshold_high' => 'decimal:4',
        'critical_threshold_low' => 'decimal:4',
        'critical_threshold_high' => 'decimal:4',
        'display_order' => 'integer',
        'show_on_dashboard' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function values()
    {
        return $this->hasMany(FinancialKpiValue::class, 'kpi_id');
    }

    public function alerts()
    {
        return $this->hasMany(FinancialKpiAlert::class, 'kpi_id');
    }

    public function latestValue()
    {
        return $this->hasOne(FinancialKpiValue::class, 'kpi_id')
            ->latest('calculation_date');
    }

    // ==========================================
    // KPI CALCULATION
    // ==========================================

    /**
     * Calculate KPI value based on formula.
     * Formula is stored as JSON with components and operations.
     */
    public function calculate(?string $asOfDate = null): ?float
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $formula = $this->calculation_formula;
        if (empty($formula)) {
            return null;
        }

        try {
            $components = $this->resolveComponents($formula['components'] ?? [], $asOfDate);

            if (empty($components)) {
                return null;
            }

            return $this->evaluateFormula($formula['expression'] ?? '', $components);

        } catch (\Exception $e) {
            \Log::error("KPI calculation failed: {$this->kpi_code}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolve formula components (account balances, etc.).
     */
    private function resolveComponents(array $components, string $asOfDate): array
    {
        $resolved = [];

        foreach ($components as $key => $component) {
            $value = match ($component['type'] ?? 'account') {
                'account' => $this->getAccountBalance($component, $asOfDate),
                'account_type' => $this->getAccountTypeBalance($component, $asOfDate),
                'constant' => $component['value'] ?? 0,
                'previous_period' => $this->getPreviousPeriodValue($component, $asOfDate),
                default => 0,
            };

            $resolved[$key] = $value;
        }

        return $resolved;
    }

    /**
     * Get account balance for component.
     */
    private function getAccountBalance(array $component, string $asOfDate): float
    {
        $accountCode = $component['account_code'] ?? null;
        if (!$accountCode) {
            return 0;
        }

        $account = Account::where('account_code', $accountCode)->first();
        if (!$account) {
            return 0;
        }

        return $account->getBalance($asOfDate);
    }

    /**
     * Get balance for account type.
     */
    private function getAccountTypeBalance(array $component, string $asOfDate): float
    {
        $accountType = $component['account_type'] ?? null;
        if (!$accountType) {
            return 0;
        }

        return Account::where('account_type', $accountType)
            ->get()
            ->sum(fn($account) => $account->getBalance($asOfDate));
    }

    /**
     * Evaluate the formula expression.
     */
    private function evaluateFormula(string $expression, array $components): float
    {
        $expression = strtolower($expression);

        // Replace component placeholders
        foreach ($components as $key => $value) {
            $expression = str_replace('{' . $key . '}', (string) $value, $expression);
        }

        // Simple expression evaluation (division, multiplication, etc.)
        // For security, we use a simple parser instead of eval()
        if (preg_match('/^\s*([\d.]+)\s*\/\s*([\d.]+)\s*$/', $expression, $matches)) {
            $divisor = (float) $matches[2];
            return $divisor != 0 ? round((float) $matches[1] / $divisor, 4) : 0;
        }

        if (preg_match('/^\s*\(([\d.]+)\s*-\s*([\d.]+)\)\s*\/\s*([\d.]+)\s*$/', $expression, $matches)) {
            $divisor = (float) $matches[3];
            $numerator = (float) $matches[1] - (float) $matches[2];
            return $divisor != 0 ? round($numerator / $divisor, 4) : 0;
        }

        // Return numeric if simple number
        if (is_numeric($expression)) {
            return (float) $expression;
        }

        return 0;
    }

    /**
     * Determine status based on thresholds.
     */
    public function getStatus(float $value): string
    {
        if ($this->critical_threshold_low !== null && $value < $this->critical_threshold_low) {
            return 'critical';
        }

        if ($this->critical_threshold_high !== null && $value > $this->critical_threshold_high) {
            return 'critical';
        }

        if ($this->warning_threshold_low !== null && $value < $this->warning_threshold_low) {
            return 'warning';
        }

        if ($this->warning_threshold_high !== null && $value > $this->warning_threshold_high) {
            return 'warning';
        }

        return 'normal';
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDashboard($query)
    {
        return $query->where('show_on_dashboard', true)
            ->where('is_active', true)
            ->orderBy('display_order');
    }

    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            self::CAT_LIQUIDITY => 'Liquidity',
            self::CAT_PROFITABILITY => 'Profitability',
            self::CAT_EFFICIENCY => 'Efficiency',
            self::CAT_SOLVENCY => 'Solvency',
            self::CAT_REVENUE => 'Revenue',
            self::CAT_CASH_FLOW => 'Cash Flow',
            default => ucfirst($this->category ?? 'Other'),
        };
    }

    public function getUnitLabelAttribute(): string
    {
        return match ($this->unit) {
            'percentage' => '%',
            'ratio' => ':1',
            'currency' => '₦',
            'days' => 'days',
            default => $this->unit,
        };
    }

    /**
     * Format value with unit.
     */
    public function formatValue(?float $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return match ($this->unit) {
            'percentage' => number_format($value * 100, 2) . '%',
            'ratio' => number_format($value, 2) . ':1',
            'currency' => '₦' . number_format($value, 2),
            'days' => number_format($value, 0) . ' days',
            default => number_format($value, 2),
        };
    }

    /**
     * Get predefined KPIs for seeding.
     */
    public static function getStandardKpis(): array
    {
        return [
            // Liquidity KPIs
            [
                'kpi_code' => 'CURRENT_RATIO',
                'kpi_name' => 'Current Ratio',
                'category' => self::CAT_LIQUIDITY,
                'description' => 'Measures ability to pay short-term obligations',
                'calculation_formula' => [
                    'expression' => '{current_assets} / {current_liabilities}',
                    'components' => [
                        'current_assets' => ['type' => 'account_type', 'account_type' => 'asset'],
                        'current_liabilities' => ['type' => 'account_type', 'account_type' => 'liability'],
                    ],
                ],
                'unit' => 'ratio',
                'target_value' => 2.0,
                'warning_threshold_low' => 1.5,
                'critical_threshold_low' => 1.0,
            ],
            [
                'kpi_code' => 'QUICK_RATIO',
                'kpi_name' => 'Quick Ratio (Acid Test)',
                'category' => self::CAT_LIQUIDITY,
                'description' => 'Measures ability to meet short-term obligations with liquid assets',
                'calculation_formula' => [
                    'expression' => '({current_assets} - {inventory}) / {current_liabilities}',
                    'components' => [
                        'current_assets' => ['type' => 'account_type', 'account_type' => 'asset'],
                        'inventory' => ['type' => 'account', 'account_code' => '1300'],
                        'current_liabilities' => ['type' => 'account_type', 'account_type' => 'liability'],
                    ],
                ],
                'unit' => 'ratio',
                'target_value' => 1.5,
                'warning_threshold_low' => 1.0,
                'critical_threshold_low' => 0.5,
            ],
            // Profitability KPIs
            [
                'kpi_code' => 'GROSS_MARGIN',
                'kpi_name' => 'Gross Profit Margin',
                'category' => self::CAT_PROFITABILITY,
                'description' => 'Revenue minus cost of goods sold as percentage of revenue',
                'calculation_formula' => [
                    'expression' => '({revenue} - {cogs}) / {revenue}',
                    'components' => [
                        'revenue' => ['type' => 'account_type', 'account_type' => 'revenue'],
                        'cogs' => ['type' => 'account', 'account_code' => '5000'],
                    ],
                ],
                'unit' => 'percentage',
                'target_value' => 0.40,
                'warning_threshold_low' => 0.30,
                'critical_threshold_low' => 0.20,
            ],
            [
                'kpi_code' => 'NET_MARGIN',
                'kpi_name' => 'Net Profit Margin',
                'category' => self::CAT_PROFITABILITY,
                'description' => 'Net income as percentage of revenue',
                'calculation_formula' => [
                    'expression' => '{net_income} / {revenue}',
                    'components' => [
                        'net_income' => ['type' => 'calculated', 'calc' => 'revenue - expenses'],
                        'revenue' => ['type' => 'account_type', 'account_type' => 'revenue'],
                    ],
                ],
                'unit' => 'percentage',
                'target_value' => 0.15,
                'warning_threshold_low' => 0.10,
                'critical_threshold_low' => 0.05,
            ],
            // Efficiency KPIs
            [
                'kpi_code' => 'AR_TURNOVER',
                'kpi_name' => 'AR Turnover (Days)',
                'category' => self::CAT_EFFICIENCY,
                'description' => 'Average days to collect receivables',
                'calculation_formula' => [
                    'expression' => '{ar_balance} / ({revenue} / 365)',
                    'components' => [
                        'ar_balance' => ['type' => 'account', 'account_code' => '1200'],
                        'revenue' => ['type' => 'account_type', 'account_type' => 'revenue'],
                    ],
                ],
                'unit' => 'days',
                'target_value' => 45,
                'warning_threshold_high' => 60,
                'critical_threshold_high' => 90,
            ],
            [
                'kpi_code' => 'AP_TURNOVER',
                'kpi_name' => 'AP Turnover (Days)',
                'category' => self::CAT_EFFICIENCY,
                'description' => 'Average days to pay suppliers',
                'calculation_formula' => [
                    'expression' => '{ap_balance} / ({purchases} / 365)',
                    'components' => [
                        'ap_balance' => ['type' => 'account', 'account_code' => '2100'],
                        'purchases' => ['type' => 'account', 'account_code' => '5000'],
                    ],
                ],
                'unit' => 'days',
                'target_value' => 30,
                'warning_threshold_high' => 45,
                'critical_threshold_high' => 60,
            ],
            // Cash Flow KPIs
            [
                'kpi_code' => 'CASH_RATIO',
                'kpi_name' => 'Cash Ratio',
                'category' => self::CAT_CASH_FLOW,
                'description' => 'Cash and equivalents to current liabilities',
                'calculation_formula' => [
                    'expression' => '{cash} / {current_liabilities}',
                    'components' => [
                        'cash' => ['type' => 'account', 'account_code' => '1010'],
                        'current_liabilities' => ['type' => 'account_type', 'account_type' => 'liability'],
                    ],
                ],
                'unit' => 'ratio',
                'target_value' => 0.5,
                'warning_threshold_low' => 0.3,
                'critical_threshold_low' => 0.1,
            ],
        ];
    }
}
