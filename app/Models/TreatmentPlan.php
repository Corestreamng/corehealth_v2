<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Treatment Plan model (CLINICAL_ORDERS_PLAN §6.2).
 *
 * Reusable template of clinical orders (labs, imaging, medications, procedures,
 * non-pharm, referrals, admissions, encounter notes) that can be applied
 * to an encounter/patient in one click.
 *
 * Plans are patient-scoped and traverse encounters — they track the originating
 * doctor, encounter, and clinic for auditability.
 */
class TreatmentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'diagnosis_data',
        'diagnosis_status',
        'diagnosis_course',
        'problem_text',
        'icd_code',
        'goal',
        'progress_percent',
        'priority',
        'specialty',
        'visibility',
        'created_by',
        'patient_id',
        'encounter_id',
        'clinic_id',
        'is_global',
        'status',
        'retired_at',
        'retired_by',
        'retirement_reason',
        'retirement_notes',
    ];

    protected $casts = [
        'is_global'        => 'boolean',
        'progress_percent' => 'integer',
        'diagnosis_data'   => 'array',
        'visibility'       => 'array',
        'retired_at'       => 'datetime',
    ];

    /**
     * Check if a given user and/or role/department has visibility access to this plan.
     */
    public function isAccessibleBy($user = null, ?string $roleOrDepartment = null): bool
    {
        $user = $user ?? \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return false;
        }

        // Creator and Admins always have access
        if ((int) $this->created_by === (int) $user->id) {
            return true;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole(['SUPERADMIN', 'ADMIN', 'Super Admin', 'Admin'])) {
            return true;
        }

        // Global plans or empty/null visibility means visible to all roles
        if (empty($this->visibility) || !is_array($this->visibility) || in_array('all', $this->visibility) || $this->is_global) {
            return true;
        }

        $allowed = array_map('strtolower', $this->visibility);

        if ($roleOrDepartment && in_array(strtolower($roleOrDepartment), $allowed)) {
            return true;
        }

        if ($user->staff && $user->staff->role && in_array(strtolower($user->staff->role->name ?? ''), $allowed)) {
            return true;
        }

        if (method_exists($user, 'getRoleNames')) {
            $userRoles = $user->getRoleNames()->map(fn($r) => strtolower($r))->toArray();
            if (count(array_intersect($userRoles, $allowed)) > 0) {
                return true;
            }
        }

        return false;
    }

    /* ──────────── Relationships ──────────── */

    public function items()
    {
        return $this->hasMany(TreatmentPlanItem::class)->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function retirer()
    {
        return $this->belongsTo(\App\Models\User::class, 'retired_by');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function labRequests()
    {
        return $this->hasMany(LabServiceRequest::class, 'treatment_plan_id');
    }

    public function imagingRequests()
    {
        return $this->hasMany(ImagingServiceRequest::class, 'treatment_plan_id');
    }

    public function productRequests()
    {
        return $this->hasMany(ProductRequest::class, 'treatment_plan_id');
    }

    public function procedures()
    {
        return $this->hasMany(Procedure::class, 'treatment_plan_id');
    }

    public function nonPharmOrders()
    {
        return $this->hasMany(NonPharmOrder::class, 'treatment_plan_id');
    }

    /* ──────────── Linked Order Items (across 8 tables) ──────────── */

    /**
     * Fetch all items across all 8 order tables linked to this plan.
     * Returns a keyed collection of model collections.
     */
    public function linkedItems(): Collection
    {
        return collect([
            'labs'       => LabServiceRequest::with('service')->where('treatment_plan_id', $this->id)->get(),
            'imaging'    => ImagingServiceRequest::with('service')->where('treatment_plan_id', $this->id)->get(),
            'medications'=> ProductRequest::with('product')->where('treatment_plan_id', $this->id)->get(),
            'procedures' => Procedure::with(['service', 'procedureDefinition'])->where('treatment_plan_id', $this->id)->get(),
            'non_pharm'  => NonPharmOrder::where('treatment_plan_id', $this->id)->get(),
            'referrals'  => SpecialistReferral::where('treatment_plan_id', $this->id)->get(),
            'admissions' => AdmissionRequest::where('treatment_plan_id', $this->id)->get(),
            'notes'      => Encounter::where('treatment_plan_id', $this->id)->get(),
        ]);
    }

    /**
     * Auto-compute progress_percent from linked item statuses.
     *
     * Completion rules per model:
     *   - LabServiceRequest:    status 4 (approved/completed)
     *   - ImagingServiceRequest: status 4
     *   - ProductRequest:       dispensed_by IS NOT NULL
     *   - Procedure:            procedure_status = 'completed'
     *   - NonPharmOrder:        status = 'completed'
     *   - SpecialistReferral:   status = 'completed'
     *   - AdmissionRequest:     discharged = 1
     *   - Encounter (notes):    completed = 1
     */
    public function computeProgress(): int
    {
        $linked = $this->linkedItems();
        $total = 0;
        $score = 0.0;

        $requireLabApproval = (bool) appsettings('lab_results_require_approval', false);
        $requireImgApproval = (bool) appsettings('imaging_results_require_approval', false);

        foreach ($linked as $type => $items) {
            $total += $items->count();
            foreach ($items as $item) {
                switch ($type) {
                    case 'labs':
                    case 'imaging':
                        $st = (int) ($item->status ?? 0);
                        $hasResult = !empty($item->result) || !empty($item->result_by) || !empty($item->result_date);
                        $isFreeForm = !empty($item->is_free_form);
                        $requiresApproval = ($type === 'labs') ? $requireLabApproval : $requireImgApproval;

                        if ($st >= 4 || !empty($item->approved_by) || !empty($item->approved_at) || ($hasResult && (!$requiresApproval || $isFreeForm)) || ($st >= 3 && !$requiresApproval)) {
                            $score += 1.0; // Completed / Finalized (100%)
                        } elseif ($st == 3 || $hasResult) {
                            $score += 0.75; // Result Entered / Pending Approval (75%)
                        } elseif ($st == 2 || !empty($item->billed_by) || !empty($item->billed_date) || !empty($item->billing_id) || !empty($item->biller_id) || (isset($item->payment_status) && $item->payment_status === 'paid')) {
                            $score += 0.50; // Billed / Sample Collected / In Progress (50%)
                        } else {
                            $score += 0.25; // Prescribed / Requested (25%)
                        }
                        break;

                    case 'medications':
                        $st = (int) ($item->status ?? 0);
                        $isFreeForm = !empty($item->is_free_form);
                        $isDispensed = !empty($item->dispensed_by) || !empty($item->dispense_date) || $st >= 3 || !empty($item->dispensed);
                        $isBilled = !empty($item->billed_by) || !empty($item->billed_date) || $st == 2 || (isset($item->payment_status) && $item->payment_status === 'paid');

                        if ($isDispensed) {
                            $score += 1.0; // Dispensed / Fully Completed (100%)
                        } elseif ($isFreeForm && $isBilled) {
                            $score += 0.75; // Freeform medication billed (75%)
                        } elseif ($isBilled) {
                            $score += 0.50; // Billed / Paid / Processing (50%)
                        } else {
                            $score += 0.25; // Prescribed / Ordered (25%)
                        }
                        break;

                    case 'procedures':
                        $pStatus = strtolower($item->procedure_status ?? '');
                        $st = (int) ($item->status ?? 0);
                        $hasPostNotes = !empty($item->post_notes) || !empty($item->post_notes_by);

                        if ($pStatus === 'completed' || $st >= 4 || $hasPostNotes) {
                            $score += 1.0; // Completed (100%)
                        } elseif ($pStatus === 'in_progress' || $st == 3 || !empty($item->actual_start_time)) {
                            $score += 0.75; // In Progress (75%)
                        } elseif ($pStatus === 'scheduled' || !empty($item->scheduled_date) || !empty($item->billed_by) || !empty($item->billed_on) || $st == 2) {
                            $score += 0.50; // Scheduled / Billed (50%)
                        } elseif ($pStatus === 'cancelled' || $st == 6 || !empty($item->cancelled_at)) {
                            $score += 0.0; // Cancelled (0%)
                        } else {
                            $score += 0.25; // Requested (25%)
                        }
                        break;

                    case 'non_pharm':
                        $st = strtolower($item->status ?? '');
                        if ($st === 'completed' || !empty($item->completed_by) || !empty($item->completed_at)) {
                            $score += 1.0; // Completed (100%)
                        } elseif ($st === 'active' || $st === 'in_progress') {
                            $score += 0.50; // Active / In Progress (50%)
                        } elseif ($st === 'discontinued' || $st === 'cancelled' || !empty($item->discontinued_at)) {
                            $score += 0.0; // Discontinued (0%)
                        } else {
                            $score += 0.25; // Pending (25%)
                        }
                        break;

                    case 'referrals':
                        $st = strtolower($item->status ?? '');
                        if ($st === 'completed') {
                            $score += 1.0; // Completed (100%)
                        } elseif ($st === 'booked' || $st === 'referred_out' || !empty($item->actioned_by) || !empty($item->actioned_at) || !empty($item->appointment_id)) {
                            $score += 0.50; // Actioned / Booked (50%)
                        } elseif ($st === 'declined' || $st === 'cancelled') {
                            $score += 0.0; // Declined / Cancelled (0%)
                        } else {
                            $score += 0.25; // Pending (25%)
                        }
                        break;

                    case 'admissions':
                        $aStatus = strtolower($item->admission_status ?? '');
                        if ((bool)$item->discharged || $aStatus === 'discharged' || !empty($item->discharged_by) || !empty($item->discharge_date)) {
                            $score += 1.0; // Discharged (100%)
                        } elseif ($aStatus === 'discharge_requested') {
                            $score += 0.75; // Discharge Requested (75%)
                        } elseif ($aStatus === 'admitted' || !empty($item->bed_id) || !empty($item->bed_assigned_by) || !empty($item->bed_assign_date)) {
                            $score += 0.50; // Admitted / Bed Assigned (50%)
                        } elseif ($aStatus === 'cancelled') {
                            $score += 0.0; // Cancelled (0%)
                        } else {
                            $score += 0.25; // Requested / Pending Checklist (25%)
                        }
                        break;

                    case 'notes':
                        if ((bool)$item->completed || !empty($item->doctor_signoff_at)) {
                            $score += 1.0; // Note Completed (100%)
                        } else {
                            $score += 0.50; // Note Open / Draft (50%)
                        }
                        break;

                    default:
                        $score += 0.25;
                        break;
                }
            }
        }

        $percent = $total > 0 ? (int) round(($score / $total) * 100) : 0;
        if ($percent > 100) $percent = 100;

        // Persist the computed progress
        $this->update(['progress_percent' => $percent]);

        return $percent;
    }

    /* ──────────── Scopes ──────────── */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRetired($query)
    {
        return $query->whereIn('status', ['retired', 'completed', 'discontinued', 'superseded']);
    }

    /**
     * Plans visible to a given user: their own + global plans.
     */
    public function scopeVisibleTo($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('created_by', $userId)
              ->orWhere('is_global', true);
        });
    }

    /**
     * All plans for a specific patient (across all encounters).
     */
    public function scopeForPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeBySpecialty($query, ?string $specialty)
    {
        if ($specialty) {
            return $query->where('specialty', $specialty);
        }
        return $query;
    }
}
