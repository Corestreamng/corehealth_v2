<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_request_id',
        'billed_by',
        'billed_date',
        'service_id',
        'encounter_id',
        'patient_id',
        'bed_id',
        'bed_assign_date',
        'bed_assigned_by',
        'discharged',
        'discharge_date',
        'discharged_by',
        'doctor_id',
        'note',
        'status',
        'admission_reason',
        'discharge_reason',
        'discharge_note',
        'followup_instructions',
        'priority'
    ];

    public function productOrServiceRequest(){
        return $this->belongsTo(ProductOrServiceRequest::class,'service_request_id','id');
    }

    public function service(){
        return $this->belongsTo(service::class, 'service_id','id');
    }

    public function encounter(){
        return $this->belongsTo(Encounter::class, 'encounter_id','id');
    }

    public function patient(){
        return $this->belongsTo(patient::class, 'patient_id','id');
    }

    public function doctor(){
        return $this->belongsTo(User::class, 'doctor_id','id');
    }

    public function biller(){
        return $this->belongsTo(User::class, 'billed_by','id');
    }

    public function bed_assigner(){
        return $this->belongsTo(User::class, 'bed_assigned_by','id');
    }

    public function discharger(){
        return $this->belongsTo(User::class, 'discharged_by','id');
    }

    public function bed(){
        return $this->hasOne(Bed::class, 'id', 'bed_id');
    }
}
