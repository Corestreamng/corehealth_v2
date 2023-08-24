<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status'
    ];

    public function staff(){
        return $this->hasMany(Staff::class,'specialization_id','id');
    }
}
