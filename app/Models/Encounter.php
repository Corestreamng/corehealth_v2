<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Encounter extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'service_request_id',
        'service_id',
        'patient_id',
        'admission_request_id',
        'reasons_for_encounter',
        'notes',
    ];

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
        return $this->belongsTo(service::class, 'service_id', 'id');
    }

    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id', 'id');
    }

    public function admission_request()
    {
        return $this->hasOne(AdmissionRequest::class, 'id', 'admission_request_id');
    }
}
