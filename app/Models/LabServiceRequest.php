<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabServiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_request_id',
        'service_id',
        'encounter_id',
        'patient_id',
        'result',
        'result_date',
        'result_by',
        'sample_taken',
        'sample_date',
        'sample_taken_by',
        'status'
    ];

    public function productOrServiceRequest(){
        return $this->belongsTo(ProductOrServiceRequest::class,'service_request_id','id');
    }

    public function service(){
        return $this->belongsTo(Service::class, 'service_id','id');
    }

    public function encounter(){
        return $this->belongsTo(Encounter::class, 'encounter_id','id');
    }

    public function patient(){
        return $this->belongsTo(patient::class, 'patient_id','id');
    }
}
