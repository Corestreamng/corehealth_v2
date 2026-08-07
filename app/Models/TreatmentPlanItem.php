<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Treatment Plan Item model (CLINICAL_ORDERS_PLAN §6.2).
 *
 * A single order line (lab, imaging, medication, procedure) within a treatment plan.
 * Uses item_type + reference_id to point at either services or products table.
 */
class TreatmentPlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_plan_id',
        'item_type',
        'reference_id',
        'dose',
        'note',
        'priority',
        'sort_order',
    ];

    /* ──────────── Relationships ──────────── */

    public function treatmentPlan()
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    /**
     * Resolve the referenced service or product based on item_type.
     * - lab, imaging, procedure → services table (App\Models\service)
     * - medication → products table (App\Models\Product)
     * - non_pharm, referral, admission, encounter_note → no direct reference
     *   (these store freeform instructions rather than service/product refs)
     *
     * WARNING (A4): This conditional belongsTo CANNOT be eager-loaded
     * (e.g. ::with('referenceable')). Eager loading executes on a blank model
     * before hydration, so item_type is always null → falls to the else branch.
     * Use direct access ($item->referenceable) or the display_name accessor instead.
     * For batch resolution, see TreatmentPlanController::show() which uses explicit find().
     */
    public function referenceable()
    {
        if ($this->item_type === 'medication') {
            return $this->belongsTo(\App\Models\Product::class, 'reference_id');
        }
        if (in_array($this->item_type, ['non_pharm', 'referral', 'admission', 'encounter_note'])) {
            // Extended types don't reference services/products — they use the note field
            // Return a null-safe belongsTo that won't crash
            return $this->belongsTo(\App\Models\Service::class, 'reference_id');
        }
        // lab, imaging, procedure → service
        return $this->belongsTo(\App\Models\Service::class, 'reference_id');
    }

    /**
     * Convenience: get the display name of the referenced item.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->item_type === 'medication') {
            return optional(\App\Models\Product::find($this->reference_id))->product_name ?? 'Unknown Product';
        }
        if ($this->item_type === 'non_pharm') {
            return $this->note ?? 'Non-Pharmacological Order';
        }
        if ($this->item_type === 'referral') {
            return $this->note ?? 'Specialist Referral';
        }
        if ($this->item_type === 'admission') {
            return $this->note ?? 'Admission Request';
        }
        if ($this->item_type === 'encounter_note') {
            return $this->note ?? 'Encounter Note';
        }
        return optional(\App\Models\Service::find($this->reference_id))->service_name ?? 'Unknown Service';
    }
}
