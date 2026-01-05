<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class Store extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'store_name',
        'location',
        'status',
    ];

    // Relationships between store model and the one listed below
    public function stock() {
        return $this->hasMany(StoreStock::class,'store_id','id');
    }
}
