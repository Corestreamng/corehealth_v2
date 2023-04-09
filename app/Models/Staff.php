<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'specialization_id',
        'clinic_id',
        'gender',
        'date_of_birth',
        'home_address',
        'phone_number',
        'consultation_fee',
        'status',
    ];

    public function user(){
        return $this->belongsTo(User::class,'user_id','id');
    }

    public function specialization(){
        return $this->hasOne(Specialization::class);
    }

    public function clinic(){
        return $this->hasOne(Clinic::class);
    }
}