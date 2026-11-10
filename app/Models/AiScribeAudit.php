<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class AiScribeAudit extends Model implements AuditableContract
{
    use HasFactory;
    use Auditable;

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'user_id',
        'role',
        'taxonomy_type',
        'original_transcript',
        'baseline_editor_content',
        'structured_note_json',
        'final_signed_note',
        'orders_extracted_json',
        'orders_dispatched_json',
        'model_used',
        'provider',
        'signed_at',
    ];

    protected $casts = [
        'structured_note_json' => 'array',
        'orders_extracted_json' => 'array',
        'orders_dispatched_json' => 'array',
        'signed_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
