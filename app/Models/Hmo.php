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
        'discount'
    ];

    public function patients(){
        return $this->hasMany(patient::class,'hmo_id','id');
    }
}
