<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class service_ extends Model
{
    use HasFactory;
    protected $fillable = ['service','patient_id'];
}
