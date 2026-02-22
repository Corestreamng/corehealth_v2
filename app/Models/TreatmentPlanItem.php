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
        // lab, imaging, procedure → service
        return $this->belongsTo(\App\Models\service::class, 'reference_id');
    }

    /**
     * Convenience: get the display name of the referenced item.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->item_type === 'medication') {
            return optional(\App\Models\Product::find($this->reference_id))->product_name ?? 'Unknown Product';
        }
        return optional(\App\Models\service::find($this->reference_id))->service_name ?? 'Unknown Service';
    }
}
