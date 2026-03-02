<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class DeliveryRecord extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'enrollment_id', 'patient_id', 'encounter_id',
        'delivery_date', 'delivery_time',
        'place_of_delivery', 'duration_of_labour_hours',
        'type_of_delivery', 'episiotomy',
        'complications', 'blood_loss_ml',
        'placenta_complete', 'placenta_notes',
        'perineal_tear_degree', 'oxytocin_given',
        'number_of_babies', 'delivered_by',
        'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'delivery_time' => 'datetime',
        'placenta_complete' => 'boolean',
        'oxytocin_given' => 'boolean',
    ];

    /* ── Relationships ─────────────────────── */

    public function enrollment()
    {
        return $this->belongsTo(MaternityEnrollment::class, 'enrollment_id');
    }

    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function babies()
    {
        return $this->hasMany(MaternityBaby::class, 'enrollment_id', 'enrollment_id');
    }

    public function partographEntries()
    {
        return $this->hasMany(DeliveryPartograph::class, 'delivery_record_id');
    }
}
