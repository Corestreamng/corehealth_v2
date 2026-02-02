<?php

namespace App\Models\HR;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * Payroll Batch Model with approval workflow
 */
class PayrollBatch extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'batch_number',
        'name',
        'pay_period_start',
        'pay_period_end',
        'work_period_start',
        'work_period_end',
        'days_in_month',
        'days_worked',
        'payment_date',
        'total_staff',
        'total_gross',
        'total_additions',
        'total_deductions',
        'total_net',
        'status',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'approval_comments',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'paid_by',
        'paid_at',
        'payment_comments',
        'expense_id',
        // Payment tracking - which bank/cash was used
        'payment_method',  // 'cash', 'bank_transfer'
        'bank_id',         // FK to banks table
        'account_id',      // FK to accounts table (GL account)
        'journal_entry_id', // FK to journal_entries table
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'work_period_start' => 'date',
        'work_period_end' => 'date',
        'payment_date' => 'date',
        'total_gross' => 'decimal:2',
        'total_additions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAID = 'paid';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->batch_number)) {
                $model->batch_number = self::generateBatchNumber();
            }
        });
    }

    /**
     * Generate unique batch number
     */
    public static function generateBatchNumber(): string
    {
        $prefix = 'PAY';
        $yearMonth = date('Ym');
        $lastBatch = self::where('batch_number', 'like', $prefix . $yearMonth . '%')
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastBatch ? (int) substr($lastBatch->batch_number, -4) + 1 : 1;
        return $prefix . $yearMonth . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get payroll items
     */
    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * Get the linked expense
     */
    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * Get the creator
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get who submitted
     */
    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get who approved
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get who rejected
     */
    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get who marked as paid
     */
    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Get the bank used for payment
     */
    public function bank()
    {
        return $this->belongsTo(\App\Models\Bank::class, 'bank_id');
    }

    /**
     * Get the GL account used for payment
     */
    public function account()
    {
        return $this->belongsTo(\App\Models\Accounting\Account::class, 'account_id');
    }

    /**
     * Get the linked journal entry
     */
    public function journalEntry()
    {
        return $this->belongsTo(\App\Models\Accounting\JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Get attachments
     */
    public function attachments()
    {
        return $this->morphMany(HrAttachment::class, 'attachable');
    }

    /**
     * Recalculate totals from items
     */
    public function recalculateTotals(): void
    {
        $this->total_staff = $this->items()->count();
        $this->total_gross = $this->items()->sum('gross_salary');
        $this->total_additions = $this->items()->sum('total_additions');
        $this->total_deductions = $this->items()->sum('total_deductions');
        $this->total_net = $this->items()->sum('net_salary');
        $this->save();
    }

    /**
     * Check if batch can be edited
     */
    public function canEdit(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if batch can be submitted
     */
    public function canSubmit(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->count() > 0;
    }

    /**
     * Check if batch can be approved
     */
    public function canApprove(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * Get status badge
     */
    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'secondary',
            self::STATUS_SUBMITTED => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_PAID => 'info',
            default => 'secondary'
        };
    }

    /**
     * Get static statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_PAID => 'Paid',
        ];
    }

    /**
     * Scope for draft batches
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope for submitted batches (pending approval)
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    /**
     * Scope for approved batches
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for paid batches
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope for rejected batches
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }
}
