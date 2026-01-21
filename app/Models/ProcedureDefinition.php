<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * ProcedureDefinition Model (Procedure Catalog)
 *
 * This model represents procedure definitions linked to services.
 * When a service belongs to the "Procedures" category, a linked
 * procedure_definition record stores additional procedure-specific metadata.
 *
 * Architecture: procedure_definitions.service_id → services.id (one-to-one)
 */
class ProcedureDefinition extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'procedure_definitions';

    protected $fillable = [
        'service_id',
        'procedure_category_id',
        'name',
        'code',
        'description',
        'is_surgical',
        'estimated_duration_minutes',
        'status',
    ];

    protected $casts = [
        'is_surgical' => 'boolean',
        'status' => 'boolean',
        'estimated_duration_minutes' => 'integer',
    ];

    /**
     * Get the parent service.
     * The procedure inherits pricing from the service.
     */
    public function service()
    {
        return $this->belongsTo(service::class, 'service_id', 'id');
    }

    /**
     * Get the procedure category (surgical specialty).
     */
    public function procedureCategory()
    {
        return $this->belongsTo(ProcedureCategory::class, 'procedure_category_id', 'id');
    }

    /**
     * Get all patient procedures of this type.
     */
    public function procedures()
    {
        return $this->hasMany(Procedure::class, 'procedure_definition_id', 'id');
    }

    /**
     * Scope to get only active procedures.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope to get only surgical procedures.
     */
    public function scopeSurgical($query)
    {
        return $query->where('is_surgical', true);
    }

    /**
     * Get the price from the linked service.
     */
    public function getPriceAttribute()
    {
        return $this->service?->price?->price ?? 0;
    }
}
