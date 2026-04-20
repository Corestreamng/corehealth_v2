<?php

namespace App\Models\HR;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class GradeLevel extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'level',
        'step',
        'description',
        'min_years_to_next',
        'retirement_age',
        'max_years_of_service',
        'min_salary',
        'max_salary',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'step' => 'integer',
        'min_years_to_next' => 'integer',
        'retirement_age' => 'integer',
        'max_years_of_service' => 'integer',
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function staff()
    {
        return $this->hasMany(Staff::class, 'grade_level_id');
    }

    public function entryStaff()
    {
        return $this->hasMany(Staff::class, 'entry_grade_level_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('level')->orderBy('step');
    }

    public function getFullNameAttribute(): string
    {
        return $this->step > 1
            ? "{$this->name} (Step {$this->step})"
            : $this->name;
    }

    public function getSalaryRangeAttribute(): ?string
    {
        if ($this->min_salary && $this->max_salary) {
            return number_format($this->min_salary, 2) . ' - ' . number_format($this->max_salary, 2);
        }
        return null;
    }
}
