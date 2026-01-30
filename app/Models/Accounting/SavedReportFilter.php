<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saved Report Filter Model
 *
 * Reference: Accounting System Plan §4.1 - Eloquent Models
 *
 * Stores user-saved filter configurations for accounting reports.
 * Allows users to save and quickly reapply complex filter combinations.
 */
class SavedReportFilter extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'report_type',
        'name',
        'filters',
        'is_default',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_default' => 'boolean',
    ];

    // Report types
    const REPORT_TRIAL_BALANCE = 'trial_balance';
    const REPORT_JOURNAL_LIST = 'journal_list';
    const REPORT_PROFIT_LOSS = 'profit_loss';
    const REPORT_BALANCE_SHEET = 'balance_sheet';
    const REPORT_CASH_FLOW = 'cash_flow';
    const REPORT_GENERAL_LEDGER = 'general_ledger';
    const REPORT_ACCOUNT_ACTIVITY = 'account_activity';

    /**
     * Get available report types.
     */
    public static function getReportTypes(): array
    {
        return [
            self::REPORT_TRIAL_BALANCE => 'Trial Balance',
            self::REPORT_JOURNAL_LIST => 'Journal List',
            self::REPORT_PROFIT_LOSS => 'Profit & Loss',
            self::REPORT_BALANCE_SHEET => 'Balance Sheet',
            self::REPORT_CASH_FLOW => 'Cash Flow Statement',
            self::REPORT_GENERAL_LEDGER => 'General Ledger',
            self::REPORT_ACCOUNT_ACTIVITY => 'Account Activity',
        ];
    }

    /**
     * Get the user who owns this filter.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a specific filter value.
     */
    public function getFilter(string $key, $default = null)
    {
        return $this->filters[$key] ?? $default;
    }

    /**
     * Set a specific filter value.
     */
    public function setFilter(string $key, $value): void
    {
        $filters = $this->filters ?? [];
        $filters[$key] = $value;
        $this->filters = $filters;
    }

    /**
     * Check if filter has a specific key.
     */
    public function hasFilter(string $key): bool
    {
        return isset($this->filters[$key]);
    }

    /**
     * Mark this filter as default and unmark others.
     */
    public function setAsDefault(): void
    {
        // Unmark other defaults for same user and report type
        self::where('user_id', $this->user_id)
            ->where('report_type', $this->report_type)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by report type.
     */
    public function scopeForReport($query, string $reportType)
    {
        return $query->where('report_type', $reportType);
    }

    /**
     * Scope to get default filters only.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get user's saved filters for a specific report.
     */
    public static function getUserFilters(int $userId, string $reportType)
    {
        return self::forUser($userId)
            ->forReport($reportType)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get user's default filter for a specific report.
     */
    public static function getUserDefault(int $userId, string $reportType): ?self
    {
        return self::forUser($userId)
            ->forReport($reportType)
            ->default()
            ->first();
    }

    /**
     * Get the report type display name.
     */
    public function getReportTypeLabelAttribute(): string
    {
        $types = self::getReportTypes();
        return $types[$this->report_type] ?? ucfirst(str_replace('_', ' ', $this->report_type));
    }

    /**
     * Get filter summary for display.
     */
    public function getFiltersSummaryAttribute(): string
    {
        $parts = [];
        $filters = $this->filters ?? [];

        if (!empty($filters['from_date'])) {
            $parts[] = 'From: ' . $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $parts[] = 'To: ' . $filters['to_date'];
        }

        if (!empty($filters['account_id'])) {
            $parts[] = 'Account filtered';
        }

        if (!empty($filters['status'])) {
            $parts[] = 'Status: ' . $filters['status'];
        }

        return $parts ? implode(', ', $parts) : 'No filters';
    }
}
