<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class PatientAccount extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'patient_id',
        'balance'
    ];


    public function patient(){
        return $this->belongsTo(patient::class, 'patient_id', 'id');
    }
}
