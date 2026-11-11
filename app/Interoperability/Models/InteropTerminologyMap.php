<?php

namespace App\Interoperability\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InteropTerminologyMap extends Model
{
    use HasFactory;

    protected $table = 'interop_terminology_maps';

    protected $fillable = [
        'standard',
        'standard_code',
        'standard_display',
        'system_uri',
        'local_entity_type',
        'local_entity_id',
        'local_key',
    ];

    /**
     * Scope to find a code mapping by standard and code.
     */
    public function scopeCode($query, string $standard, string $code)
    {
        return $query->where('standard', $standard)->where('standard_code', $code);
    }

    /**
     * Scope to find a mapping by local entity.
     */
    public function scopeForEntity($query, string $entityType, ?int $entityId = null, ?string $key = null)
    {
        $q = $query->where('local_entity_type', $entityType);
        if ($entityId !== null) {
            $q->where('local_entity_id', $entityId);
        }
        if ($key !== null) {
            $q->where('local_key', $key);
        }

        return $q;
    }
}
