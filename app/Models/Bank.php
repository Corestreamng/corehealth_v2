<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'account_number',
        'account_name',
        'bank_code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function payments()
    {
        return $this->hasMany(payment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
