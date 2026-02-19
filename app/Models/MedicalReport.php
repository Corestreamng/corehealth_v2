<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class MedicalReport extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'medical_reports';

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'doctor_id',
        'title',
        'content',
        'report_date',
        'status',
        'finalized_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'finalized_at' => 'datetime',
    ];

    /**
     * Status constants.
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_FINALIZED = 'finalized';

    /**
     * Scope: only drafts.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope: only finalized.
     */
    public function scopeFinalized($query)
    {
        return $query->where('status', self::STATUS_FINALIZED);
    }

    /**
     * Check if report is finalized.
     */
    public function isFinalized()
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    /**
     * Get the patient.
     */
    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id');
    }

    /**
     * Get the doctor (user).
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * Get the encounter.
     */
    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }
}
