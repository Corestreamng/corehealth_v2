<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hmo extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'desc',
        'status',
        'discount',
        'hmo_scheme_id'
    ];

    public function scheme()
    {
        return $this->belongsTo(HmoScheme::class, 'hmo_scheme_id');
    }

    public function patients(){
        return $this->hasMany(patient::class,'hmo_id','id');
    }
}
