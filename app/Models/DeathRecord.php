<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class DeathRecord extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'admission_request_id',
        'death_type',
        'date_of_death',
        'time_of_death',
        'cause_of_death_primary',
        'cause_of_death_description',
        'certified_by_doctor_id',
        'last_office_done',
        'last_office_by_nurse_id',
        'last_office_at',
        'disposition',
        'disposition_note',
    ];

    protected $casts = [
        'date_of_death' => 'date',
        'last_office_at' => 'datetime',
        'last_office_done' => 'boolean',
    ];

    // Relationships
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function admissionRequest()
    {
        return $this->belongsTo(AdmissionRequest::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'certified_by_doctor_id');
    }

    public function nurse()
    {
        return $this->belongsTo(User::class, 'last_office_by_nurse_id');
    }

    public function morgueAdmission()
    {
        return $this->hasOne(MorgueAdmission::class);
    }
}
