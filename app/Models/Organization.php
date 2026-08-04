<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $table = 'organizations';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Scope to only active organizations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Get all bills for this organization.
     */
    public function bills()
    {
        return $this->hasMany(OrganizationBill::class, 'organization_id');
    }

    /**
     * Get pending (unsettled) bills for this organization.
     */
    public function pendingBills()
    {
        return $this->hasMany(OrganizationBill::class, 'organization_id')
            ->whereIn('status', ['pending_audit', 'pending']);
    }

    /**
     * Get the total outstanding amount for this organization.
     */
    public function getTotalOutstandingAttribute(): float
    {
        return (float) $this->bills()
            ->whereIn('status', ['pending_audit', 'pending'])
            ->sum('outstanding_amount');
    }
}
