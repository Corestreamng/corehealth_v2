<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Encounter extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'doctor_id',
        'service_request_id',
        'service_id',
        'patient_id',
        'queue_id',
        'admission_request_id',
        'reasons_for_encounter',
        'reasons_for_encounter_comment_1',
        'reasons_for_encounter_comment_2',
        'notes',
        'completed',
        'started_at',
        'completed_at',
        'deleted_at',
        'deleted_by',
        'deletion_reason',
    ];

    protected $casts = [
        'completed'    => 'boolean',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ──────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────

    public function labRequests()
    {
        return $this->hasMany(LabServiceRequest::class, 'encounter_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id', 'id');
    }

    public function productOrServiceRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'service_request_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function admission_request()
    {
        return $this->hasOne(AdmissionRequest::class, 'id', 'admission_request_id');
    }

    public function queue()
    {
        return $this->belongsTo(DoctorQueue::class, 'queue_id', 'id');
    }

    public function referrals()
    {
        return $this->hasMany(SpecialistReferral::class, 'encounter_id', 'id');
    }
}
