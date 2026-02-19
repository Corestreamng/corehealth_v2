<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


use OwenIt\Auditing\Contracts\Auditable;
class MedicationHistory extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'patient_id',
        'product_or_service_request_id',
        'action',
        'reason',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the patient associated with this history record.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(patient::class);
    }

    /**
     * Get the product or service request associated with this history record.
     */
    public function productOrServiceRequest(): BelongsTo
    {
        return $this->belongsTo(ProductOrServiceRequest::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
