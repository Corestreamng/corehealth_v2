<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Patient extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'user_id',
        'file_no',
        'insurance_scheme',
        'hmo_id',
        'hmo_no',
        'is_deceased',
        'date_of_death',
        'gender',
        'dob',
        'blood_group',
        'genotype',
        'disability',
        'address',
        'phone_no',
        'nationality',
        'ethnicity',
        'misc',
        'allergies',
        'medical_history',
        'next_of_kin_name',
        'next_of_kin_phone',
        'next_of_kin_address',
        'dhis_consult_tracker_id',
        'dhis_consult_enrollment_id',
        'is_family_principal',
        'principal_id',
        'default_billing_mode',
        'default_billing_id',
        'role',
        'is_hmo_principal',
        'hmo_principal_id',
        'hmo_dependent_role',
        'registration_workbench',
        'registered_by',
    ];

    protected $appends = [
        'registration_workbench_label',
        'registered_on',
        'registered_by_name',
        'can_edit',
    ];

    protected $casts = [
        'allergies' => 'array',
        'dob' => 'date',
        'is_deceased' => 'boolean',
        'date_of_death' => 'date',
    ];

    public function deathRecord()
    {
        return $this->hasOne(DeathRecord::class);
    }

    public function maternityEnrollments()
    {
        return $this->hasMany(MaternityEnrollment::class);
    }

    public function morgueAdmissions()
    {
        return $this->hasMany(MorgueAdmission::class);
    }

    public function hmoPrincipal()
    {
        return $this->belongsTo(Patient::class, 'hmo_principal_id');
    }

    public function hmoDependents()
    {
        return $this->hasMany(Patient::class, 'hmo_principal_id');
    }

    public function morgueAdmission()
    {
        return $this->hasOne(MorgueAdmission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function hmo()
    {
        return $this->belongsTo(Hmo::class, 'hmo_id', 'id');
    }

    public function account()
    {
        return $this->hasOne(PatientAccount::class, 'patient_id', 'id');
    }

    public function encounters()
    {
        return $this->hasMany(Encounter::class, 'patient_id');
    }

    public function principal()
    {
        return $this->belongsTo(Patient::class, 'principal_id');
    }

    public function beneficiaries()
    {
        return $this->hasMany(Patient::class, 'principal_id');
    }

    /**
     * Get the default billing organization (if default_billing_mode is BILL_TO_ORGANIZATION).
     */
    public function defaultBillingOrganization()
    {
        return $this->belongsTo(Organization::class, 'default_billing_id');
    }

    /**
     * Get the default billing staff user (if default_billing_mode is BILL_TO_STAFF).
     */
    public function defaultBillingStaff()
    {
        return $this->belongsTo(User::class, 'default_billing_id');
    }

    /**
     * Get all staff bills for this patient.
     */
    public function staffBills()
    {
        return $this->hasMany(StaffBill::class, 'patient_id');
    }

    /**
     * Get all organization bills for this patient.
     */
    public function organizationBills()
    {
        return $this->hasMany(OrganizationBill::class, 'patient_id');
    }

    public function getFamilyPatientIdsAttribute()
    {
        $query = Patient::query();

        $query->where(function ($q) {
            if ($this->principal_id) {
                $q->where('principal_id', $this->principal_id)
                  ->orWhere('id', $this->principal_id);
            } else {
                $q->where('principal_id', $this->id)
                  ->orWhere('id', $this->id);
            }
        });

        if (!empty($this->file_no)) {
            $query->orWhere('file_no', $this->file_no);
        }

        return $query->pluck('id')->toArray();
    }

    public function getFamilyUserIdsAttribute()
    {
        $query = Patient::query();

        $query->where(function ($q) {
            if ($this->principal_id) {
                $q->where('principal_id', $this->principal_id)
                  ->orWhere('id', $this->principal_id);
            } else {
                $q->where('principal_id', $this->id)
                  ->orWhere('id', $this->id);
            }
        });

        if (!empty($this->file_no)) {
            $query->orWhere('file_no', $this->file_no);
        }

        return $query->pluck('user_id')->toArray();
    }

    public function getBillingPatientIdAttribute()
    {
        return $this->principal_id ?? $this->id;
    }

    public function scopeSearchByTerm($query, $term)
    {
        if (empty($term)) {
            return $query;
        }

        $terms = array_filter(explode(' ', trim($term)));

        return $query->where(function ($q) use ($terms, $term) {
            $q->whereHas('user', function ($u) use ($terms, $term) {
                // Ensure all parts of a spaced term match somewhere in the name
                foreach ($terms as $t) {
                    $u->where(function ($uSub) use ($t) {
                        $uSub->where('surname', 'like', "%{$t}%")
                             ->orWhere('firstname', 'like', "%{$t}%")
                             ->orWhere('othername', 'like', "%{$t}%");
                    });
                }
                // Fallbacks for full name exact match
                $u->orWhereRaw("CONCAT(firstname, ' ', surname) LIKE ?", ["%{$term}%"])
                  ->orWhereRaw("CONCAT(surname, ' ', firstname) LIKE ?", ["%{$term}%"]);
            })
            ->orWhere('file_no', 'like', "%{$term}%")
            ->orWhere('phone_no', 'like', "%{$term}%");
        })->orderByRaw("
            CASE 
                WHEN file_no LIKE ? THEN 1 
                WHEN phone_no LIKE ? THEN 3 
                ELSE 2 
            END ASC
        ", ["%{$term}%", "%{$term}%"]);
    }

    /**
     * Get human-readable label for the originating registration workbench.
     */
    public function getRegistrationWorkbenchLabelAttribute(): string
    {
        return match($this->registration_workbench) {
            'reception' => 'Reception',
            'pharmacy' => 'Pharmacy',
            'lab' => 'Medical Lab',
            'imaging' => 'Imaging / Radiology',
            'nursing' => 'Nursing',
            'maternity' => 'Maternity',
            'hmo' => 'HMO / Insurance',
            'billing' => 'Billing',
            'emergency' => 'Emergency Intake',
            'doctor' => 'Doctor Consultation',
            'surgery' => 'Surgery / Theatre',
            'morgue' => 'Morgue / Mortuary',
            'cockpit' => 'Operational Cockpit',
            'patients_directory' => 'Patients Directory',
            'import' => 'Data Import',
            default => ucfirst($this->registration_workbench ?? 'Reception'),
        };
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Check if a given staff user can edit this patient's demographics.
     * - Reception, SuperAdmin, Admin can edit ALL patients.
     * - Departmental staff can edit patients registered by their department or by themselves.
     */
    public function canBeEditedBy($user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return false;
        }

        // SuperAdmin, Admin, Receptionists can edit ANY patient
        if (
            $user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin', 'RECEPTIONIST', 'Receptionist'])
            || in_array($user->is_admin, [1, 2])
        ) {
            return true;
        }

        // Directly registered by this staff user
        if ($this->registered_by && (int) $this->registered_by === (int) $user->id) {
            return true;
        }

        // Workbench origin matches user's department/role
        $workbench = strtolower($this->registration_workbench ?? '');
        $roleMap = [
            'reception' => ['RECEPTIONIST', 'Receptionist'],
            'pharmacy' => ['PHARMACIST', 'Pharmacist'],
            'lab' => ['LAB SCIENTIST', 'Lab Scientist'],
            'imaging' => ['RADIOLOGIST', 'Radiologist', 'Scanning Room'],
            'nursing' => ['NURSE', 'Nurse'],
            'maternity' => ['MATERNITY', 'Maternity'],
            'hmo' => ['HMO Executive', 'HMO'],
            'billing' => ['BILLER', 'Biller'],
            'doctor' => ['DOCTOR', 'Doctor'],
            'surgery' => ['SURGEON', 'Surgeon', 'DOCTOR', 'Doctor', 'NURSE', 'Nurse'],
            'emergency' => ['NURSE', 'Nurse', 'DOCTOR', 'Doctor'],
            'morgue' => ['MORGUE', 'Morgue', 'Mortuary'],
        ];

        if (isset($roleMap[$workbench]) && $user->hasAnyRole($roleMap[$workbench])) {
            return true;
        }

        return false;
    }

    /**
     * Accessor indicating if currently authenticated staff can edit this patient.
     */
    public function getCanEditAttribute(): bool
    {
        return $this->canBeEditedBy(auth()->user());
    }

    /**
     * Human-readable registration datetime string.
     */
    public function getRegisteredOnAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('M d, Y h:i A') : 'N/A';
    }

    /**
     * Human-readable name of staff member who registered this patient.
     */
    public function getRegisteredByNameAttribute(): string
    {
        if ($this->relationLoaded('registeredBy') && $this->registeredBy) {
            return trim($this->registeredBy->surname . ' ' . $this->registeredBy->firstname . ' ' . ($this->registeredBy->othername ?? ''));
        }
        if ($this->registered_by) {
            $staff = User::find($this->registered_by);
            if ($staff) {
                return trim($staff->surname . ' ' . $staff->firstname . ' ' . ($staff->othername ?? ''));
            }

            return 'Staff #' . $this->registered_by;
        }

        return 'System / Reception';
    }

    /**
     * Standardized prefix assigned to patient file numbers generated by specific workbenches.
     */
    public const WORKBENCH_PREFIXES = [
        'lab' => 'LAB-',
        'pharmacy' => 'PHARM-',
        'maternity' => 'ANC-',
        'imaging' => 'RAD-',
        'nursing' => 'NUR-',
        'emergency' => 'EMG-',
        'billing' => 'BIL-',
        'hmo' => 'HMO-',
        'surgery' => 'SURG-',
        'morgue' => 'MOR-',
        'doctor' => 'DOC-',
    ];

    /**
     * Legacy and active file number prefixes mapped to their corresponding workbenches for backfills and auditing.
     */
    public const LEGACY_PREFIX_MAP = [
        'LAB' => 'lab',
        'PHARM' => 'pharmacy',
        'PHM' => 'pharmacy',
        'ANC' => 'maternity',
        'PANC' => 'maternity',
        'RAD' => 'imaging',
        'IMG' => 'imaging',
        'NUR' => 'nursing',
        'WRD' => 'nursing',
        'EMG' => 'emergency',
        'EX' => 'emergency',
        'BIL' => 'billing',
        'HMO' => 'hmo',
        'SURG' => 'surgery',
        'TH' => 'surgery',
        'MOR' => 'morgue',
        'DOC' => 'doctor',
    ];

    /**
     * Get the authoritative fixed prefix for a given workbench.
     */
    public static function getPrefixForWorkbench(?string $workbench): ?string
    {
        if (!$workbench) {
            return null;
        }

        return self::WORKBENCH_PREFIXES[strtolower(trim($workbench))] ?? null;
    }

    /**
     * Resolve the originating workbench based on a patient's file number prefix.
     */
    public static function resolveWorkbenchFromFileNo(?string $fileNo): ?string
    {
        if (!$fileNo) {
            return null;
        }
        $upper = strtoupper(trim($fileNo));
        foreach (self::LEGACY_PREFIX_MAP as $prefix => $wb) {
            if (str_starts_with($upper, $prefix . '-') || str_starts_with($upper, $prefix)) {
                return $wb;
            }
        }

        return null;
    }
}
