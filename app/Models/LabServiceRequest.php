<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


use OwenIt\Auditing\Contracts\Auditable;
class LabServiceRequest extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'service_request_id',
        'billed_by',
        'billed_date',
        'service_id',
        'encounter_id',
        'patient_id',
        'result',
        'result_data',
        'attachments',
        'result_date',
        'result_by',
        'sample_taken',
        'sample_date',
        'sample_taken_by',
        'doctor_id',
        'note',
        'status',
        'deleted_by',
        'deletion_reason',
        'dismissed_at',
        'dismissed_by',
        'dismiss_reason'
    ];

    protected $casts = [
        'attachments' => 'array',
        'result_data' => 'array'
    ];

    public function productOrServiceRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'service_request_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo(service::class, 'service_id', 'id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id', 'id');
    }

    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id', 'id');
    }

    public function biller()
    {
        return $this->belongsTo(User::class, 'billed_by', 'id');
    }

    public function results_person()
    {
        return $this->belongsTo(User::class, 'result_by', 'id');
    }

    public function resultBy()
    {
        return $this->belongsTo(User::class, 'result_by', 'id');
    }
}
