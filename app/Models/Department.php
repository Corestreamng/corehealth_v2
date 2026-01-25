<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Department Model
 * Used for organizational structure and leave approval workflow
 */
class Department extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'code',
        'description',
        'head_of_department_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all staff in this department
     */
    public function staff()
    {
        return $this->hasMany(Staff::class, 'department_id');
    }

    /**
     * Get the head of department (user)
     */
    public function headOfDepartment()
    {
        return $this->belongsTo(User::class, 'head_of_department_id');
    }

    /**
     * Scope for active departments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordering by name
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }
}
