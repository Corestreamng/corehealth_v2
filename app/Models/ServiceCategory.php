<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_name',
        'category_code',
        'category_description',
        'status'
    ];

    public function services(){
        return $this->hasMany(service::class,'category_id','id');
    }
}
