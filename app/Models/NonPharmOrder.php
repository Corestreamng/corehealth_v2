<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NonPharmOrder extends Model
{
    use HasFactory;

    protected $table = 'non_pharm_orders';

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'maternity_enrollment_id',
        'requested_by',
        'category',
        'target_executor',
        'instructions',
        'frequency',
        'duration',
        'status',
        'completed_by',
        'completed_at',
        'discontinued_by',
        'discontinued_at',
        'discontinue_reason',
        'completed_notes',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'discontinued_at' => 'datetime',
    ];

    /**
     * Scope to return active orders
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to return completed orders
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to return discontinued orders
     */
    public function scopeDiscontinued($query)
    {
        return $query->where('status', 'discontinued');
    }

    /**
     * Scope to return orders meant for bedside execution by nursing
     */
    public function scopeNurseBedside($query)
    {
        return $query->where('target_executor', 'nurse');
    }

    /**
     * Scope to return orders meant for outpatient home/lifestyle guidance
     */
    public function scopePatientHome($query)
    {
        return $query->where('target_executor', 'patient');
    }

    /**
     * Relation to patient
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Relation to encounter
     */
    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }

    /**
     * Relation to maternity enrollment
     */
    public function maternityEnrollment()
    {
        return $this->belongsTo(MaternityEnrollment::class, 'maternity_enrollment_id');
    }

    /**
     * Relation to requesting user
     */
    public function requestedByUser()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Relation to completing user (nurse)
     */
    public function completedByUser()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Relation to discontinuing user
     */
    public function discontinuedByUser()
    {
        return $this->belongsTo(User::class, 'discontinued_by');
    }
}
