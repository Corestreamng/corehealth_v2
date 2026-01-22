<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Model: StoreRequisition
 *
 * Plan Reference: Phase 2 - Models
 * Purpose: Represents an inter-store stock transfer request
 *
 * Workflow:
 * 1. Create requisition (from store requests items from another store)
 * 2. Admin/Store manager approves or rejects
 * 3. Source store fulfills the request
 * 4. Items transferred: source batch deducted, destination batch created
 *
 * Related Models: StoreRequisitionItem, Store, User
 * Related Files:
 * - app/Services/RequisitionService.php
 * - app/Http/Controllers/StoreRequisitionController.php
 * - database/migrations/2026_01_21_100005_create_store_requisitions_table.php
 */
class StoreRequisition extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'requisition_number',
        'from_store_id',
        'to_store_id',
        'requested_by',
        'approved_by',
        'rejected_by',
        'fulfilled_by',
        'status',
        'request_notes',
        'approval_notes',
        'rejection_reason',
        'approved_at',
        'rejected_at',
        'fulfilled_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PARTIAL = 'partial';
    const STATUS_FULFILLED = 'fulfilled';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_PARTIAL => 'Partially Fulfilled',
            self::STATUS_FULFILLED => 'Fulfilled',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->requisition_number)) {
                $model->requisition_number = self::generateRequisitionNumber();
            }
            if (empty($model->requested_by)) {
                $model->requested_by = auth()->id();
            }
        });
    }

    /**
     * Generate a unique requisition number
     */
    public static function generateRequisitionNumber(): string
    {
        $prefix = 'REQ';
        $year = date('Y');
        $month = date('m');

        $lastReq = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastReq ? (intval(substr($lastReq->requisition_number, -4)) + 1) : 1;

        return sprintf('%s%s%s%04d', $prefix, $year, $month, $sequence);
    }

    // ===== RELATIONSHIPS =====

    /**
     * Get the source store (where items come from)
     */
    public function fromStore()
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    /**
     * Get the destination store (where items go to)
     */
    public function toStore()
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    /**
     * Get the user who requested
     */
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who rejected
     */
    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the user who fulfilled
     */
    public function fulfiller()
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    /**
     * Get the requisition items
     */
    public function items()
    {
        return $this->hasMany(StoreRequisitionItem::class);
    }

    /**
     * Get stock batches created from this requisition (at destination)
     */
    public function destinationBatches()
    {
        return $this->hasMany(StockBatch::class, 'source_requisition_id');
    }

    // ===== SCOPES =====

    /**
     * Scope for pending requisitions
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved requisitions (awaiting fulfillment)
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for requisitions that can be fulfilled
     */
    public function scopeFulfillable($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PARTIAL]);
    }

    /**
     * Scope for requisitions from a specific store
     */
    public function scopeFromStore($query, int $storeId)
    {
        return $query->where('from_store_id', $storeId);
    }

    /**
     * Scope for requisitions to a specific store
     */
    public function scopeToStore($query, int $storeId)
    {
        return $query->where('to_store_id', $storeId);
    }

    /**
     * Scope for requisitions requested by a user
     */
    public function scopeRequestedBy($query, int $userId)
    {
        return $query->where('requested_by', $userId);
    }

    // ===== HELPERS =====

    /**
     * Check if requisition can be approved
     */
    public function canApprove(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if requisition can be rejected
     */
    public function canReject(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if requisition can be fulfilled
     */
    public function canFulfill(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PARTIAL]);
    }

    /**
     * Check if requisition can be cancelled
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Approve this requisition
     */
    public function approve(?string $notes = null): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->approved_by = auth()->id();
        $this->approved_at = now();
        $this->approval_notes = $notes;
        $this->save();

        // Update all items to approved
        $this->items()->update(['status' => StoreRequisitionItem::STATUS_APPROVED]);
    }

    /**
     * Reject this requisition
     */
    public function reject(string $reason): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejected_by = auth()->id();
        $this->rejected_at = now();
        $this->rejection_reason = $reason;
        $this->save();

        // Update all items to rejected
        $this->items()->update(['status' => StoreRequisitionItem::STATUS_REJECTED]);
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'badge-warning',
            self::STATUS_APPROVED => 'badge-primary',
            self::STATUS_REJECTED => 'badge-danger',
            self::STATUS_PARTIAL => 'badge-info',
            self::STATUS_FULFILLED => 'badge-success',
            self::STATUS_CANCELLED => 'badge-secondary',
            default => 'badge-secondary',
        };
    }
}
