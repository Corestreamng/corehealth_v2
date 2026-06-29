<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class Patient extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'user_id',
        'file_no',
        'insurance_scheme',
        'hmo_id',
        'hmo_no',
        'is_deceased',
        'date_of_death',
        'gender',
        'dob',
        'blood_group',
        'genotype',
        'disability',
        'address',
        'phone_no',
        'nationality',
        'ethnicity',
        'misc',
        'allergies',
        'medical_history',
        'next_of_kin_name',
        'next_of_kin_phone',
        'next_of_kin_address',
        'dhis_consult_tracker_id',
        'dhis_consult_enrollment_id',
        'is_family_principal',
        'principal_id'
    ];

    protected $casts = [
        'allergies' => 'array',
        'dob' => 'date',
        'is_deceased' => 'boolean',
        'date_of_death' => 'date',
    ];

    public function deathRecord()
    {
        return $this->hasOne(DeathRecord::class);
    }

    public function morgueAdmission()
    {
        return $this->hasOne(MorgueAdmission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function hmo()
    {
        return $this->belongsTo(Hmo::class, 'hmo_id', 'id');
    }

    public function account()
    {
        return $this->hasOne(PatientAccount::class, 'patient_id', 'id');
    }

    public function principal()
    {
        return $this->belongsTo(Patient::class, 'principal_id');
    }

    public function beneficiaries()
    {
        return $this->hasMany(Patient::class, 'principal_id');
    }

    public function getFamilyUserIdsAttribute()
    {
        if ($this->principal_id) {
            return Patient::where('principal_id', $this->principal_id)
                ->orWhere('id', $this->principal_id)
                ->pluck('user_id')->toArray();
        }
        return Patient::where('principal_id', $this->id)
            ->orWhere('id', $this->id)
            ->pluck('user_id')->toArray();
    }

    public function getBillingPatientIdAttribute()
    {
        return $this->principal_id ?? $this->id;
    }
}
