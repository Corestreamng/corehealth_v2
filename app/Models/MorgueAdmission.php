<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class MorgueAdmission extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'death_record_id',
        'patient_id',
        'body_code',
        'fridge_number',
        'tray_number',
        'daily_service_id',
        'admitted_by_staff_id',
        'arrival_time',
        'release_time',
        'released_by_staff_id',
        'released_to_name',
        'released_to_id_type',
        'released_to_id_no',
        'status',
        'notes',
        'current_service_request_id',
    ];

    protected $casts = [
        'arrival_time' => 'datetime',
        'release_time' => 'datetime',
    ];

    // Relationships
    public function deathRecord()
    {
        return $this->belongsTo(DeathRecord::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function dailyService()
    {
        return $this->belongsTo(Service::class, 'daily_service_id');
    }

    public function admittedBy()
    {
        return $this->belongsTo(User::class, 'admitted_by_staff_id');
    }

    public function releasedBy()
    {
        return $this->belongsTo(User::class, 'released_by_staff_id');
    }

    public function serviceRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'current_service_request_id');
    }
}
