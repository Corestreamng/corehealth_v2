<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosisFavorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'name',
        'diagnoses',
    ];

    protected $casts = [
        'diagnoses' => 'array',
    ];

    // =====================
    // Relationships
    // =====================

    public function doctor()
    {
        return $this->belongsTo(\App\Models\User::class, 'doctor_id');
    }
}
