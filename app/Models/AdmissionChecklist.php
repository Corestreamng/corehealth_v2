<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * AdmissionChecklist Model
 *
 * Tracks completion of admission checklist for a specific admission.
 *
 * Workflow:
 * 1. Doctor creates AdmissionRequest
 * 2. System creates AdmissionChecklist from default template
 * 3. Nurse completes checklist items
 * 4. Once complete, nurse can assign bed
 * 5. Admission is finalized
 *
 * Status flow:
 * - pending → in_progress → completed
 * - or pending → waived (with reason)
 *
 * @see App\Models\AdmissionRequest
 * @see App\Models\AdmissionChecklistItem
 * @see App\Models\ChecklistTemplate
 */
class AdmissionChecklist extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'admission_request_id',
        'template_id',
        'status',
        'started_at',
        'completed_at',
        'completed_by',
        'waiver_reason',
        'waived_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_WAIVED = 'waived';

    // =====================
    // Relationships
    // =====================

    /**
     * The admission request this checklist belongs to
     */
    public function admissionRequest()
    {
        return $this->belongsTo(AdmissionRequest::class, 'admission_request_id');
    }

    /**
     * Template used for this checklist
     */
    public function template()
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }

    /**
     * Checklist items
     */
    public function items()
    {
        return $this->hasMany(AdmissionChecklistItem::class, 'admission_checklist_id');
    }

    /**
     * User who completed the checklist
     */
    public function completedByUser()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * User who waived the checklist
     */
    public function waivedByUser()
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    // =====================
    // Accessors
    // =====================

    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute(): int
    {
        $total = $this->items()->count();
        if ($total === 0) {
            return 0;
        }

        $completed = $this->items()->where('is_completed', true)->count();
        return (int) round(($completed / $total) * 100);
    }

    /**
     * Get required items count
     */
    public function getRequiredCountAttribute(): int
    {
        return $this->items()->where('is_required', true)->count();
    }

    /**
     * Get completed required items count
     */
    public function getCompletedRequiredCountAttribute(): int
    {
        return $this->items()
            ->where('is_required', true)
            ->where('is_completed', true)
            ->count();
    }

    /**
     * Check if all required items are completed
     */
    public function getIsReadyAttribute(): bool
    {
        return $this->completed_required_count === $this->required_count;
    }

    // =====================
    // Methods
    // =====================

    /**
     * Create checklist from template for an admission
     */
    public static function createFromTemplate(
        int $admissionRequestId,
        ?ChecklistTemplate $template = null
    ): self {
        $template = $template ?? ChecklistTemplate::getDefaultAdmission();

        $checklist = self::create([
            'admission_request_id' => $admissionRequestId,
            'template_id' => $template?->id,
            'status' => self::STATUS_PENDING,
        ]);

        // Copy template items if template exists
        if ($template) {
            foreach ($template->items as $templateItem) {
                AdmissionChecklistItem::create([
                    'admission_checklist_id' => $checklist->id,
                    'template_item_id' => $templateItem->id,
                    'item_text' => $templateItem->item_text,
                    'is_required' => $templateItem->is_required,
                ]);
            }
        }

        return $checklist;
    }

    /**
     * Complete a checklist item
     */
    public function completeItem(int $itemId, int $userId, ?string $comment = null): bool
    {
        $item = $this->items()->find($itemId);
        if (!$item) {
            return false;
        }

        $item->update([
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => $userId,
            'comment' => $comment,
        ]);

        // Update checklist status
        $this->updateStatus($userId);

        return true;
    }

    /**
     * Uncomplete a checklist item
     */
    public function uncompleteItem(int $itemId): bool
    {
        $item = $this->items()->find($itemId);
        if (!$item) {
            return false;
        }

        $item->update([
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
            'comment' => null,
        ]);

        // Update checklist status
        if ($this->status === self::STATUS_COMPLETED) {
            $this->update([
                'status' => self::STATUS_IN_PROGRESS,
                'completed_at' => null,
                'completed_by' => null,
            ]);
        }

        return true;
    }

    /**
     * Update checklist status based on item completion
     */
    public function updateStatus(int $userId): void
    {
        $completedCount = $this->items()->where('is_completed', true)->count();
        $totalCount = $this->items()->count();

        if ($completedCount === 0) {
            $this->update(['status' => self::STATUS_PENDING]);
        } elseif ($this->is_ready) {
            $this->update([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => now(),
                'completed_by' => $userId,
            ]);

            // Update admission request status
            $this->admissionRequest->update([
                'admission_status' => 'checklist_complete',
            ]);
        } else {
            if ($this->status === self::STATUS_PENDING) {
                $this->update([
                    'status' => self::STATUS_IN_PROGRESS,
                    'started_at' => now(),
                ]);
            }
        }
    }

    /**
     * Waive the checklist (skip with reason)
     */
    public function waive(int $userId, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_WAIVED,
            'waiver_reason' => $reason,
            'waived_by' => $userId,
            'completed_at' => now(),
        ]);

        // Update admission request status
        $this->admissionRequest->update([
            'admission_status' => 'checklist_complete',
        ]);
    }
}
