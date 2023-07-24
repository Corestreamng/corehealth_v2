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
        'status'
    ];

    public function labRequests(){
        return $this->hasMany(LabRequest::class,'encounter_id','id');
    }

    public function doctor(){
        return $this->belongsTo(User::class, 'doctor_id', 'id');
    }

    public function productOrServiceRequest(){
        return $this->belongsTo(ProductOrServiceRequest::class,'service_request_id','id');
    }

    public function service(){
        return $this->belongsTo(Service::class, 'service_id','id');
    }

    public function patient(){
        return $this->belongsTo(patient::class, 'patient_id','id');
    }

    public function admission_request(){
        return $this->hasOne(AdmissionRequest::class, 'admission_request_id', 'id');
    }
}

