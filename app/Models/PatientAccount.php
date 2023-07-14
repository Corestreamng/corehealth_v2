<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'balance'
    ];


    public function patient(){
        return $this->belongsTo(patient::class, 'patient_id', 'id');
    }
}
