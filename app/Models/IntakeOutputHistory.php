<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntakeOutputHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_id',
        'record_id',
        'user_id',
        'action',
        'reason',
        'original_values',
        'new_values'
    ];

    protected $casts = [
        'original_values' => 'array',
        'new_values' => 'array',
    ];

    public function period()
    {
        return $this->belongsTo(IntakeOutputPeriod::class, 'period_id');
    }

    public function record()
    {
        return $this->belongsTo(IntakeOutputRecord::class, 'record_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
