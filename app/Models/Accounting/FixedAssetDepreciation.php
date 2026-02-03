<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixed Asset Depreciation Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 4.1B
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 5.5
 *
 * Monthly depreciation log for fixed assets.
 * Each record creates a journal entry through the DepreciationObserver.
 */
class FixedAssetDepreciation extends Model
{
    use HasFactory;

    protected $table = 'fixed_asset_depreciations';

    // Calculation Methods
    public const METHOD_SCHEDULED = 'scheduled';
    public const METHOD_CATCH_UP = 'catch_up';
    public const METHOD_ADJUSTMENT = 'adjustment';
    public const METHOD_IMPAIRMENT = 'impairment';

    protected $fillable = [
        'fixed_asset_id',
        'journal_entry_id',
        'fiscal_year_id',
        'depreciation_date',
        'year_number',
        'month_number',
        'opening_book_value',
        'depreciation_amount',
        'closing_book_value',
        'accumulated_depreciation_to_date',
        'calculation_method',
        'notes',
        'processed_by',
    ];

    protected $casts = [
        'depreciation_date' => 'date',
        'year_number' => 'integer',
        'month_number' => 'integer',
        'opening_book_value' => 'decimal:2',
        'depreciation_amount' => 'decimal:2',
        'closing_book_value' => 'decimal:2',
        'accumulated_depreciation_to_date' => 'decimal:2',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function processedByUser()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForAsset($query, int $assetId)
    {
        return $query->where('fixed_asset_id', $assetId);
    }

    public function scopeForPeriod($query, string $fromDate, string $toDate)
    {
        return $query->whereBetween('depreciation_date', [$fromDate, $toDate]);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('depreciation_date', $year);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('depreciation_date', $year)
            ->whereMonth('depreciation_date', $month);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Accessor for 'amount' - alias for depreciation_amount
     */
    public function getAmountAttribute(): float
    {
        return (float) $this->depreciation_amount;
    }

    /**
     * Accessor for 'book_value_after' - alias for closing_book_value
     */
    public function getBookValueAfterAttribute(): float
    {
        return (float) $this->closing_book_value;
    }

    /**
     * Get method label.
     */
    public function getMethodLabelAttribute(): string
    {
        return match ($this->calculation_method) {
            self::METHOD_SCHEDULED => 'Scheduled',
            self::METHOD_CATCH_UP => 'Catch-up',
            self::METHOD_ADJUSTMENT => 'Adjustment',
            self::METHOD_IMPAIRMENT => 'Impairment',
            default => ucfirst($this->calculation_method ?? 'Unknown'),
        };
    }

    /**
     * Get period display.
     */
    public function getPeriodDisplayAttribute(): string
    {
        return "Year {$this->year_number}, Month {$this->month_number}";
    }
}
