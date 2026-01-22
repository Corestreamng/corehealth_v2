<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Model: Expense
 *
 * Plan Reference: Phase 2 - Models
 * Purpose: Track all expenses including PO-related purchases and general store expenses
 *
 * Features:
 * - Polymorphic reference to link to PO or other sources
 * - Category-based expense tracking
 * - Approval workflow
 *
 * Related Models: PurchaseOrder, Supplier, Store, User
 * Related Files:
 * - app/Http/Controllers/ExpenseController.php
 * - database/migrations/2026_01_21_100007_create_expenses_table.php
 */
class Expense extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'expense_number',
        'category',
        'reference_type',
        'reference_id',
        'amount',
        'supplier_id',
        'store_id',
        'title',
        'description',
        'expense_date',
        'recorded_by',
        'approved_by',
        'status',
        'rejection_reason',
        'approved_at',
        'payment_method',
        'payment_reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Category constants
     */
    const CATEGORY_PURCHASE_ORDER = 'purchase_order';
    const CATEGORY_STORE_EXPENSE = 'store_expense';
    const CATEGORY_MAINTENANCE = 'maintenance';
    const CATEGORY_UTILITIES = 'utilities';
    const CATEGORY_SALARIES = 'salaries';
    const CATEGORY_OTHER = 'other';

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_VOID = 'void';

    /**
     * Get all categories
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_PURCHASE_ORDER => 'Purchase Order',
            self::CATEGORY_STORE_EXPENSE => 'Store Expense',
            self::CATEGORY_MAINTENANCE => 'Maintenance',
            self::CATEGORY_UTILITIES => 'Utilities',
            self::CATEGORY_SALARIES => 'Salaries',
            self::CATEGORY_OTHER => 'Other',
        ];
    }

    /**
     * Get all statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_VOID => 'Void',
        ];
    }

    /**
     * Get payment methods
     */
    public static function getPaymentMethods(): array
    {
        return [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
            'mobile_money' => 'Mobile Money',
            'credit' => 'Credit',
            'other' => 'Other',
        ];
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->expense_number)) {
                $model->expense_number = self::generateExpenseNumber();
            }
            if (empty($model->recorded_by)) {
                $model->recorded_by = auth()->id();
            }
            if (empty($model->expense_date)) {
                $model->expense_date = now();
            }
        });
    }

    /**
     * Generate a unique expense number
     */
    public static function generateExpenseNumber(): string
    {
        $prefix = 'EXP';
        $year = date('Y');
        $month = date('m');

        $lastExp = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastExp ? (intval(substr($lastExp->expense_number, -4)) + 1) : 1;

        return sprintf('%s%s%s%04d', $prefix, $year, $month, $sequence);
    }

    // ===== RELATIONSHIPS =====

    /**
     * Get the polymorphic reference (PurchaseOrder, etc.)
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Get the supplier
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the store
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the user who recorded this expense
     */
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the user who approved this expense
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ===== SCOPES =====

    /**
     * Scope for expenses of a category
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for pending expenses
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved expenses
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for expenses in a date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    /**
     * Scope for expenses for a store
     */
    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    // ===== HELPERS =====

    /**
     * Create an expense from a Purchase Order
     */
    public static function createFromPurchaseOrder(PurchaseOrder $po): self
    {
        return self::create([
            'category' => self::CATEGORY_PURCHASE_ORDER,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
            'amount' => $po->total_amount,
            'supplier_id' => $po->supplier_id,
            'store_id' => $po->target_store_id,
            'title' => "Purchase Order: {$po->po_number}",
            'description' => "Expense for PO {$po->po_number} from supplier",
            'expense_date' => $po->received_date ?? now(),
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Approve this expense
     */
    public function approve(): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->approved_by = auth()->id();
        $this->approved_at = now();
        $this->save();
    }

    /**
     * Reject this expense
     */
    public function reject(string $reason): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejection_reason = $reason;
        $this->save();
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'badge-warning',
            self::STATUS_APPROVED => 'badge-success',
            self::STATUS_REJECTED => 'badge-danger',
            self::STATUS_VOID => 'badge-secondary',
            default => 'badge-secondary',
        };
    }

    /**
     * Get category badge class for UI
     */
    public function getCategoryBadgeClass(): string
    {
        return match($this->category) {
            self::CATEGORY_PURCHASE_ORDER => 'badge-primary',
            self::CATEGORY_STORE_EXPENSE => 'badge-info',
            self::CATEGORY_MAINTENANCE => 'badge-warning',
            self::CATEGORY_UTILITIES => 'badge-success',
            self::CATEGORY_SALARIES => 'badge-danger',
            default => 'badge-secondary',
        };
    }
}
