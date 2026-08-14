<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\IsAuditable;

class PatientAccount extends Model implements Auditable
{
    use IsAuditable;

    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'patient_id',
        'balance'
    ];


    public function patient(){
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }
}
