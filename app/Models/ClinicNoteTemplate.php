<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ClinicNoteTemplate extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'clinic_note_templates';

    protected $fillable = [
        'clinic_id',
        'name',
        'description',
        'content',
        'category',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope: only active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: for a specific clinic (includes global templates where clinic_id is null)
     */
    public function scopeForClinic($query, $clinicId)
    {
        return $query->where(function ($q) use ($clinicId) {
            $q->where('clinic_id', $clinicId)
              ->orWhereNull('clinic_id');
        });
    }

    /**
     * Get the clinic this template belongs to.
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Get the user who created this template.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
