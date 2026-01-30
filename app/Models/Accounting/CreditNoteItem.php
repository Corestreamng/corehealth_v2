<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Credit Note Item Model
 *
 * Reference: Accounting System Plan §4.1 - Eloquent Models
 *
 * Individual line item within a credit note.
 * Links back to original billing items being refunded.
 */
class CreditNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_id',
        'original_item_type',
        'original_item_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:4',
        'amount' => 'decimal:4',
    ];

    // Common original item types
    const ITEM_BILLING_ITEM = 'App\\Models\\BillingItem';
    const ITEM_PAYMENT = 'App\\Models\\Payment';
    const ITEM_SERVICE_CHARGE = 'App\\Models\\ServiceCharge';

    /**
     * Get the credit note this item belongs to.
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * Get the original billing item (polymorphic).
     */
    public function originalItem(): MorphTo
    {
        return $this->morphTo('original_item');
    }

    /**
     * Calculate the amount from quantity and unit price.
     */
    public function calculateAmount(): float
    {
        return round($this->quantity * $this->unit_price, 4);
    }

    /**
     * Recalculate and save amount.
     */
    public function recalculateAmount(): void
    {
        $this->amount = $this->calculateAmount();
        $this->save();
    }

    /**
     * Boot method to auto-calculate amount.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            if ($item->quantity && $item->unit_price) {
                $item->amount = round($item->quantity * $item->unit_price, 4);
            }
        });

        static::saved(function ($item) {
            // Update credit note total when item is saved
            if ($item->creditNote) {
                $item->creditNote->recalculateTotal();
            }
        });

        static::deleted(function ($item) {
            // Update credit note total when item is deleted
            if ($item->creditNote) {
                $item->creditNote->recalculateTotal();
            }
        });
    }

    /**
     * Get formatted amount for display.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2);
    }

    /**
     * Get formatted unit price for display.
     */
    public function getFormattedUnitPriceAttribute(): string
    {
        return number_format($this->unit_price, 2);
    }

    /**
     * Get the original item type short name.
     */
    public function getOriginalItemTypeShortAttribute(): string
    {
        if (!$this->original_item_type) {
            return 'Manual';
        }

        return class_basename($this->original_item_type);
    }
}
