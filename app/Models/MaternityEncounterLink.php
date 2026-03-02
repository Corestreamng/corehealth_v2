<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class MaternityEncounterLink extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'enrollment_id', 'encounter_id', 'visit_type', 'notes',
    ];

    /* ── Relationships ─────────────────────── */

    public function enrollment()
    {
        return $this->belongsTo(MaternityEnrollment::class, 'enrollment_id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }
}
