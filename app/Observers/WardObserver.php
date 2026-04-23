<?php

namespace App\Observers;

use App\Models\Ward;
use App\Models\Store;
use Illuminate\Support\Str;

/**
 * WardObserver — auto-creates a ward stock store whenever a new Ward is created.
 *
 * Purpose: every ward must have a corresponding Store so that NursingShift
 * step-2 resolution in StoreContextResolver always has a store to return.
 *
 * Store properties set:
 *   - store_name      → "{$ward->name} Store"
 *   - code            → derived from ward code
 *   - store_type      → 'ward'
 *   - distribution_role → Store::ROLE_WARD
 *   - ward_id         → $ward->id
 *   - requires_shift_context → true  (shift must be active to use this store)
 *   - status          → 1 (active)
 *   - is_default      → false
 *   - is_immutable    → false  (ward stores can be edited; only canonical stores are immutable)
 */
class WardObserver
{
    public function created(Ward $ward): void
    {
        // Skip if a store for this ward already exists (idempotent)
        $exists = Store::where('ward_id', $ward->id)->exists();
        if ($exists) {
            return;
        }

        $code = $ward->code
            ? Str::upper($ward->code) . '_WS'
            : 'W' . $ward->id . '_WS';

        Store::create([
            'store_name'             => $ward->name . ' Store',
            'code'                   => $code,
            'description'            => "Auto-created ward store for {$ward->name}",
            'location'               => $ward->floor ?? $ward->name,
            'store_type'             => 'ward',
            'distribution_role'      => Store::ROLE_WARD,
            'ward_id'                => $ward->id,
            'requires_shift_context' => true,
            'allows_direct_patient_dispense' => false,
            'status'                 => 1,
            'is_default'             => false,
            'is_immutable'           => false,
        ]);
    }
}
