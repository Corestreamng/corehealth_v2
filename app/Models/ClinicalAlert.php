<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicalAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'created_by',
        'alert_text',
        'severity',
        'visibility',
        'is_active',
    ];

    protected $casts = [
        'visibility' => 'array',
        'is_active' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator()
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }
}
