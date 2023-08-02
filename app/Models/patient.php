<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_no',
        'insurance_scheme',
        'hmo_id',
        'hmo_no',
        'gender',
        'dob',
        'blood_group',
        'genotype',
        'disability',
        'address',
        'nationality',
        'ethnicity',
        'misc'
    ];

    public function user(){
        return $this->belongsTo(User::class,'user_id','id');
    }

    public function hmo(){
        return $this->belongsTo(Hmo::class,'hmo_id', 'id');
    }

    public function account(){
        return $this->hasOne(PatientAccount::class, 'patient_id', 'id');
    }

}
