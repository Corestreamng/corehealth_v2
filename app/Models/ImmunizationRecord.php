<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ImmunizationRecord extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'patient_id',
        'product_id',
        'product_or_service_request_id',
        'vaccine_name',
        'dose_number',
        'dose',
        'route',
        'site',
        'administered_at',
        'administered_by',
        'batch_number',
        'manufacturer',
        'expiry_date',
        'next_due_date',
        'adverse_reaction',
        'notes',
    ];

    protected $casts = [
        'administered_at' => 'datetime',
        'expiry_date' => 'date',
        'next_due_date' => 'date',
    ];

    /**
     * Get the patient that received the immunization.
     */
    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id');
    }

    /**
     * Get the product (vaccine) used.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the billing record for this immunization.
     */
    public function productOrServiceRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'product_or_service_request_id');
    }

    /**
     * Get the nurse who administered the vaccine.
     */
    public function administeredBy()
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    /**
     * Alias for administeredBy for backward compatibility.
     */
    public function nurse()
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    /**
     * Get the next dose number for a vaccine for a patient.
     */
    public static function getNextDoseNumber($patientId, $vaccineName)
    {
        $lastDose = self::where('patient_id', $patientId)
            ->where('vaccine_name', $vaccineName)
            ->orderBy('dose_number', 'desc')
            ->first();

        return $lastDose ? $lastDose->dose_number + 1 : 1;
    }

    /**
     * Check if a vaccine is due for a patient.
     */
    public static function isDue($patientId, $vaccineName)
    {
        $lastRecord = self::where('patient_id', $patientId)
            ->where('vaccine_name', $vaccineName)
            ->orderBy('administered_at', 'desc')
            ->first();

        if (!$lastRecord) {
            return true; // Never received, so it's due
        }

        if ($lastRecord->next_due_date && $lastRecord->next_due_date <= now()) {
            return true;
        }

        return false;
    }
}
