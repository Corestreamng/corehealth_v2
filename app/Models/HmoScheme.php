<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class HmoScheme extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'name',
        'code',
        'description',
        'status'
    ];

    public function hmos()
    {
        return $this->hasMany(Hmo::class, 'hmo_scheme_id');
    }
}
