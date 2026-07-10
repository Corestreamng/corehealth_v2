<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'store_id',
        'product_id',
        'audit_stamp_id',
        'system_value',
        'physical_value',
        'variance',
        'notes',
        'auditor_id',
    ];

    /**
     * Get the store.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the audit stamp.
     */
    public function auditStamp()
    {
        return $this->belongsTo(AuditStamp::class);
    }

    /**
     * Get the auditor user.
     */
    public function auditor()
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
}
