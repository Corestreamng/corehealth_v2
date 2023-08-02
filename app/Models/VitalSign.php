<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VitalSign extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_by',
        'taken_by',
        'patient_id',
        'blood_pressure',
        'temp',
        'heart_rate',
        'resp_rate',
        'other_notes',
        'time_taken',
        'status'
    ];

    public function takenBy(){
        return $this->belongsTo(User::class, 'taken_by', 'id');
    }

    public function requstedBy(){
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }

    public function patient(){
        return $this->belongsTo(patient::class, 'patient_id', 'id');
    }
}
