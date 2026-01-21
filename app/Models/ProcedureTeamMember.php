<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ProcedureTeamMember extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'procedure_id',
        'user_id',
        'role',
        'custom_role',
        'is_lead',
        'notes',
    ];

    protected $casts = [
        'is_lead' => 'boolean',
    ];

    /**
     * Available surgical team roles
     */
    const ROLES = [
        'chief_surgeon' => 'Chief Surgeon',
        'assistant_surgeon' => 'Assistant Surgeon',
        'anesthesiologist' => 'Anesthesiologist',
        'nurse_anesthetist' => 'Nurse Anesthetist',
        'scrub_nurse' => 'Scrub Nurse',
        'circulating_nurse' => 'Circulating Nurse',
        'surgical_first_assistant' => 'Surgical First Assistant',
        'perfusionist' => 'Perfusionist',
        'radiologist' => 'Radiologist',
        'pathologist' => 'Pathologist',
        'other' => 'Other',
    ];

    /**
     * Get the procedure.
     */
    public function procedure()
    {
        return $this->belongsTo(Procedure::class, 'procedure_id', 'id');
    }

    /**
     * Get the user (staff member).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get the display name for the role.
     * Returns custom_role if role is 'other', otherwise returns the role label.
     */
    public function getRoleDisplayAttribute()
    {
        if ($this->role === 'other' && $this->custom_role) {
            return $this->custom_role;
        }
        return self::ROLES[$this->role] ?? ucfirst(str_replace('_', ' ', $this->role));
    }

    /**
     * Scope to get lead team members.
     */
    public function scopeLeads($query)
    {
        return $query->where('is_lead', true);
    }

    /**
     * Scope to get team members by role.
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }
}
