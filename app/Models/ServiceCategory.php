<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class ServiceCategory extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
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
