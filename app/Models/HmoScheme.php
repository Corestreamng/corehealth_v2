<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HmoScheme extends Model
{
    use HasFactory;

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
