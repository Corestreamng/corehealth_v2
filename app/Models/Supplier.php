<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Model: Supplier
 *
 * Purpose: Represents suppliers/vendors who provide products
 *
 * Related Models: StockBatch, PurchaseOrder, StockInvoice, ProductCategory
 */
class Supplier extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'company_name',
        'contact_person',
        'email',
        'address',
        'phone',
        'alt_phone',
        'tax_number',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'payment_terms',
        'credit_limit',
        'notes',
        'created_by',
        'last_payment',
        'last_payment_date',
        'last_buy_date',
        'last_buy_amount',
        'credit',
        'deposit',
        'total_deposite',
        'date_line',
        'status',
    ];

    protected $casts = [
        'last_payment' => 'decimal:2',
        'last_buy_amount' => 'decimal:2',
        'credit' => 'decimal:2',
        'deposit' => 'decimal:2',
        'total_deposite' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'status' => 'boolean',
        'last_payment_date' => 'datetime',
        'last_buy_date' => 'datetime',
        'date_line' => 'datetime',
    ];

    // ===== RELATIONSHIPS =====

    /**
     * Stock invoices from this supplier
     */
    public function invoices()
    {
        return $this->hasMany(StockInvoice::class);
    }

    /**
     * Purchase orders to this supplier
     */
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Stock batches received from this supplier
     */
    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Product category association (if any)
     */
    public function category()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /**
     * User who created this supplier
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ===== SCOPES =====

    /**
     * Active suppliers only
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Search by name or contact
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('company_name', 'like', "%{$term}%")
              ->orWhere('contact_person', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    // ===== ACCESSORS =====

    /**
     * Get total amount supplied (from batches)
     */
    public function getTotalSuppliedValueAttribute(): float
    {
        return $this->stockBatches()
            ->sum(\DB::raw('initial_qty * cost_price'));
    }

    /**
     * Get total batches count
     */
    public function getTotalBatchesAttribute(): int
    {
        return $this->stockBatches()->count();
    }

    /**
     * Get active batches count (batches with stock)
     */
    public function getActiveBatchesAttribute(): int
    {
        return $this->stockBatches()
            ->where('current_qty', '>', 0)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Get outstanding balance (credit - deposit)
     */
    public function getOutstandingBalanceAttribute(): float
    {
        return ($this->credit ?? 0) - ($this->deposit ?? 0);
    }

    /**
     * Get last transaction date (most recent between payment and buy)
     */
    public function getLastActivityAttribute()
    {
        $dates = array_filter([
            $this->last_payment_date,
            $this->last_buy_date,
        ]);

        return $dates ? max($dates) : null;
    }
}
