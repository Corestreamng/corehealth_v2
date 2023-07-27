<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bed extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ward',
        'unit',
        'price',
        'status',
        'service_id',
        'occupant_id'
    ];

    public function admissions(){
        return $this->hasMany(AdmissionRequest::class, 'bed_id','id');
    }

    public function occupant(){
        return $this->belongsTo(patient::class, 'occupant_id', 'id');
    }

    public function service(){
        return $this->hasOne(service::class, 'id', 'service_id');
    }
}
