<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Treatment Plan model (CLINICAL_ORDERS_PLAN §6.2).
 *
 * Reusable template of clinical orders (labs, imaging, medications, procedures)
 * that can be applied to an encounter/patient in one click.
 */
class TreatmentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'specialty',
        'created_by',
        'is_global',
        'status',
    ];

    protected $casts = [
        'is_global' => 'boolean',
    ];

    /* ──────────── Relationships ──────────── */

    public function items()
    {
        return $this->hasMany(TreatmentPlanItem::class)->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /* ──────────── Scopes ──────────── */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Plans visible to a given user: their own + global plans.
     */
    public function scopeVisibleTo($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('created_by', $userId)
              ->orWhere('is_global', true);
        });
    }

    public function scopeBySpecialty($query, ?string $specialty)
    {
        if ($specialty) {
            return $query->where('specialty', $specialty);
        }
        return $query;
    }
}
