<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ProcedureCategory extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'code',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get all procedure definitions in this category.
     */
    public function procedureDefinitions()
    {
        return $this->hasMany(ProcedureDefinition::class, 'procedure_category_id', 'id');
    }

    /**
     * Alias for procedureDefinitions (for backwards compatibility).
     */
    public function procedures()
    {
        return $this->procedureDefinitions();
    }

    /**
     * Scope to get only active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
