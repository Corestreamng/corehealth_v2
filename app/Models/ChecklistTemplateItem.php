<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ChecklistTemplateItem Model
 *
 * Individual items within a checklist template.
 * These are copied to actual checklist items when a checklist is created.
 *
 * @see App\Models\ChecklistTemplate
 */
class ChecklistTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'item_text',
        'guidance',
        'is_required',
        'requires_comment',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'requires_comment' => 'boolean',
        'sort_order' => 'integer',
    ];

    // =====================
    // Relationships
    // =====================

    /**
     * Parent template
     */
    public function template()
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }
}
