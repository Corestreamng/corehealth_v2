<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditMark extends Model
{
    use HasFactory;

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'zone_key',
        'auditor_id',
        'status',
        'query_notes',
        'query_resolved_by',
        'query_resolved_at',
        'query_resolution_notes',
    ];

    public function auditable()
    {
        return $this->morphTo();
    }

    public function auditor()
    {
        return $this->belongsTo(\App\Models\User::class, 'auditor_id');
    }

    public function resolver()
    {
        return $this->belongsTo(\App\Models\User::class, 'query_resolved_by');
    }
}
