<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class MaternityPreviousPregnancy extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'enrollment_id', 'year', 'place_of_delivery',
        'duration_weeks', 'complications', 'type_of_labour',
        'baby_alive', 'baby_dead', 'baby_stillbirth',
        'baby_sex', 'birth_weight_kg', 'present_health',
        'age_at_death', 'notes',
    ];

    protected $casts = [
        'baby_alive' => 'boolean',
        'baby_dead' => 'boolean',
        'baby_stillbirth' => 'boolean',
        'birth_weight_kg' => 'decimal:3',
    ];

    public function enrollment()
    {
        return $this->belongsTo(MaternityEnrollment::class, 'enrollment_id');
    }

    public function setBabyAliveAttribute($value)
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $this->attributes['baby_alive'] = $bool ? 1 : 0;
    }

    public function setBabyDeadAttribute($value)
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $this->attributes['baby_dead'] = $bool ? 1 : 0;
    }

    public function setBabyStillbirthAttribute($value)
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $this->attributes['baby_stillbirth'] = $bool ? 1 : 0;
    }
}
