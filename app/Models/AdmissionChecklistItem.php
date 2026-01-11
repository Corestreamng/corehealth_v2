<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * AdmissionChecklistItem Model
 *
 * Individual completed items within an admission checklist.
 *
 * @see App\Models\AdmissionChecklist
 */
class AdmissionChecklistItem extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'admission_checklist_id',
        'template_item_id',
        'item_text',
        'is_required',
        'is_completed',
        'completed_at',
        'completed_by',
        'comment',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    // =====================
    // Relationships
    // =====================

    /**
     * Parent checklist
     */
    public function checklist()
    {
        return $this->belongsTo(AdmissionChecklist::class, 'admission_checklist_id');
    }

    /**
     * Original template item
     */
    public function templateItem()
    {
        return $this->belongsTo(ChecklistTemplateItem::class, 'template_item_id');
    }

    /**
     * User who completed this item
     */
    public function completedByUser()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
