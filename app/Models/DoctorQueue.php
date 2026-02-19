<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;

class DoctorQueue extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'patient_id',
        'clinic_id',
        'staff_id',
        'receptionist_id',
        'request_entry_id',
        'status',
        'vitals_taken',
        'priority',
        'source',
        'triage_note',
    ];

    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id', 'id');
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'staff_id', 'id');
    }

    public function receptionist()
    {
        return $this->belongsTo(User::class, 'receptionist_id', 'id');
    }

    public function request_entry()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'request_entry_id', 'id');
    }
}
