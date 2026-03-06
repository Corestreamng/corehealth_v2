<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HmoClaim extends Model
{
    use HasFactory;

    protected $table = 'hmo_claims';

    protected $fillable = [
        'hmo_id',
        'patient_id',
        'payment_id',
        'claims_amount',
        'status',
        'created_by',
        'processed_by',
        'processed_at',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'claims_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the HMO that this claim belongs to.
     */
    public function hmo()
    {
        return $this->belongsTo(Hmo::class, 'hmo_id');
    }

    /**
     * Get the patient that this claim is for.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Get the payment associated with this claim.
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /**
     * Get the user who created this claim.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who processed this claim.
     */
    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by HMO
     */
    public function scopeForHmo($query, $hmoId)
    {
        return $query->where('hmo_id', $hmoId);
    }

    /**
     * Scope to get pending claims
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved claims
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
