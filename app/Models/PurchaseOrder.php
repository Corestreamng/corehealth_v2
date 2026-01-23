<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Model: PurchaseOrder
 *
 * Plan Reference: Phase 2 - Models
 * Purpose: Represents a purchase order for procuring inventory
 *
 * Workflow:
 * 1. Create PO (draft) → 2. Submit for approval → 3. Approve → 4. Receive items → 5. Record expense
 *
 * Related Models: PurchaseOrderItem, Supplier, Store, User, Expense
 * Related Files:
 * - app/Services/PurchaseOrderService.php
 * - app/Http/Controllers/PurchaseOrderController.php
 * - database/migrations/2026_01_21_100001_create_purchase_orders_table.php
 */
class PurchaseOrder extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'target_store_id',
        'created_by',
        'approved_by',
        'status',
        'payment_status',
        'expected_date',
        'total_amount',
        'amount_paid',
        'notes',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'expected_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_PARTIAL = 'partial';
    const STATUS_RECEIVED = 'received';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Payment status constants
     */
    const PAYMENT_UNPAID = 'unpaid';
    const PAYMENT_PARTIAL = 'partial';
    const PAYMENT_PAID = 'paid';

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PARTIAL => 'Partially Received',
            self::STATUS_RECEIVED => 'Fully Received',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Get all available payment statuses
     */
    public static function getPaymentStatuses(): array
    {
        return [
            self::PAYMENT_UNPAID => 'Unpaid',
            self::PAYMENT_PARTIAL => 'Partially Paid',
            self::PAYMENT_PAID => 'Paid',
        ];
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->po_number)) {
                $model->po_number = self::generatePoNumber();
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    /**
     * Generate a unique PO number
     */
    public static function generatePoNumber(): string
    {
        $prefix = 'PO';
        $year = date('Y');
        $month = date('m');

        $lastPo = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastPo ? (intval(substr($lastPo->po_number, -4)) + 1) : 1;

        return sprintf('%s%s%s%04d', $prefix, $year, $month, $sequence);
    }

    // ===== RELATIONSHIPS =====

    /**
     * Get the supplier for this PO
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the target store
     */
    public function targetStore()
    {
        return $this->belongsTo(Store::class, 'target_store_id');
    }

    /**
     * Get the user who created this PO
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this PO
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the PO items
     */
    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get the expense record for this PO
     */
    public function expense()
    {
        return $this->morphOne(Expense::class, 'reference');
    }

    /**
     * Get all payments for this PO
     */
    public function payments()
    {
        return $this->hasMany(PurchaseOrderPayment::class);
    }

    /**
     * Get all stock batches created from this PO
     */
    public function stockBatches()
    {
        return $this->hasManyThrough(
            StockBatch::class,
            PurchaseOrderItem::class,
            'purchase_order_id',
            'purchase_order_item_id'
        );
    }

    // ===== SCOPES =====

    /**
     * Scope for draft POs
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope for submitted POs (pending approval)
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    /**
     * Scope for approved POs (pending receiving)
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for POs that can be received
     */
    public function scopeReceivable($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PARTIAL]);
    }

    // ===== HELPERS =====

    /**
     * Check if PO can be edited
     */
    public function canEdit(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if PO can be submitted
     */
    public function canSubmit(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->count() > 0;
    }

    /**
     * Check if PO can be approved
     */
    public function canApprove(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * Check if PO can receive items
     */
    public function canReceive(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PARTIAL]);
    }

    /**
     * Check if PO can be cancelled
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_APPROVED]);
    }

    /**
     * Calculate total from items
     */
    public function calculateTotal(): float
    {
        return $this->items->sum(function ($item) {
            return $item->ordered_qty * $item->unit_cost;
        });
    }

    /**
     * Update total amount from items
     */
    public function updateTotal(): void
    {
        $this->total_amount = $this->calculateTotal();
        $this->save();
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'badge-secondary',
            self::STATUS_SUBMITTED => 'badge-info',
            self::STATUS_APPROVED => 'badge-primary',
            self::STATUS_PARTIAL => 'badge-warning',
            self::STATUS_RECEIVED => 'badge-success',
            self::STATUS_CANCELLED => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    /**
     * Get payment status badge class for UI
     */
    public function getPaymentStatusBadgeClass(): string
    {
        return match($this->payment_status) {
            self::PAYMENT_UNPAID => 'badge-danger',
            self::PAYMENT_PARTIAL => 'badge-warning',
            self::PAYMENT_PAID => 'badge-success',
            default => 'badge-secondary',
        };
    }

    /**
     * Get balance due amount
     */
    public function getBalanceDueAttribute(): float
    {
        return max(0, (float)$this->total_amount - (float)$this->amount_paid);
    }

    /**
     * Check if PO can accept payments
     */
    public function canRecordPayment(): bool
    {
        // Can record payment only for received/partial POs that still have balance
        return in_array($this->status, [self::STATUS_PARTIAL, self::STATUS_RECEIVED])
            && $this->balance_due > 0;
    }

    /**
     * Check if PO is fully paid
     */
    public function isFullyPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    /**
     * Update payment status based on amount paid
     */
    public function updatePaymentStatus(): void
    {
        $totalPaid = $this->payments()->sum('amount');
        $this->amount_paid = $totalPaid;

        if ($totalPaid >= $this->total_amount) {
            $this->payment_status = self::PAYMENT_PAID;
        } elseif ($totalPaid > 0) {
            $this->payment_status = self::PAYMENT_PARTIAL;
        } else {
            $this->payment_status = self::PAYMENT_UNPAID;
        }

        $this->save();
    }
}
