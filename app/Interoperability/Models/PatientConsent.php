<?php

namespace App\Interoperability\Models;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientConsent extends Model
{
    use HasFactory;

    protected $table = 'patient_consents';

    protected $fillable = [
        'patient_id',
        'consent_type',
        'status',
        'ndpa_lawful_basis',
        'granted_by',
        'ip_address',
        'notes',
        'granted_at',
        'revoked_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Check if a patient has active consent for a specific processing type.
     */
    public static function hasActiveConsent(int $patientId, string $consentType): bool
    {
        return static::where('patient_id', $patientId)
            ->where(function ($q) use ($consentType) {
                $q->where('consent_type', $consentType)
                    ->orWhere('consent_type', 'ALL');
            })
            ->where('status', 'ACTIVE')
            ->exists();
    }
}
